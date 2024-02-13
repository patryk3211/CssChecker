<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Sprawdzanie pliku styli</h1>
    <?php
    require 'checker.php';

    // Mnożniki punktów do obliczania wyniku
    const SELECTOR_POINTS_MULTIPLIER = 2; // Posiadanie wszystkich selektorów może być lepiej punktowane, ponieważ jest ich mniej niż samych właściwości
    const PROPERTY_POINTS_MULTIPLIER = 1;
    const PROPERTY_VALUE_POINTS_MULTIPLIER = 1;
    // Nie licz dodatkowych właściwości jako błędy
    $GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'] = true;

    // Zmienna globalna. Folder do którego zapisywane są pliki.
    $folder = 'pliki';
    // Zmienna globalna. Nazwa pliku wzoru kodu.
    $template = 'wzor.css';

    function sprawdz_plik($uploadedFile) {
        if (!file_exists($GLOBALS['folder'] . '/' . $GLOBALS['template'])) {
            echo "<h2>Brak pliku wzoru, plik nie zostanie sprawdzony</h2>";
            return;
        }
        $template = file_get_contents($GLOBALS['folder'] . '/' . $GLOBALS['template']);
        try {
            $template = convert_css($template);
        } catch (\Sabberworm\CSS\Parsing\SourceException $e) {
            echo '<h2>Błąd we wzorze styli</h2>' . $e->getMessage();
            return;
        } catch (Exception $e) {
            echo '<h2>Błąd podczas interpretacji wzoru</h2>' . $e->getMessage();
            return;
        }

        // Sprawdzanie nazwy pliku
        $fileName = $uploadedFile['name'];
        if (!str_ends_with($fileName, '.css')) {
            echo "<h2>Nieprawidłowe rozszerzenie pliku</h2>";
            return;
        }

        $extPos = strpos($fileName, '.');
        $fileName = substr($fileName, 0, $extPos);

        $splitPos = strpos($fileName, '_');
        $nr = substr($fileName, 0, $splitPos);
        $rest = substr($fileName, $splitPos + 1);

        $splitPos = strpos($rest, '_');
        $imie = substr($rest, 0, $splitPos);
        $nazwisko = substr($rest, $splitPos + 1);
        $ip = $_SERVER['REMOTE_ADDR'];

        $finalFileName = $fileName . ' - ' . $ip . '.css';
        /*if(file_exists($GLOBALS['folder'].'/'.$finalFileName)) {
            echo "<h2>Plik już istnieje</h2>";
            return;
        }*/

        // Porównaj pliki
        $fileContent = file_get_contents($uploadedFile['tmp_name']);
        $report = check_css($template, $fileContent);
        if (is_string($report)) {
            echo $report;
            return;
        }

        // Oblicz wynik z różnic
        $templateSelectorCount = count($template->elements);
        $templatePropertyCount = 0;
        foreach ($template->elements as $element) {
            $templatePropertyCount += count($element->rules);
        }

        // Maksymalna liczba punktów to suma selektorów, właściwości oraz ich prawidłowych wartości
        $maxScore =
            $templateSelectorCount * SELECTOR_POINTS_MULTIPLIER +
            $templatePropertyCount * PROPERTY_POINTS_MULTIPLIER +
            $templatePropertyCount * PROPERTY_VALUE_POINTS_MULTIPLIER;
        $score = $maxScore
            - $report->get_error_count('MissingSelector') * SELECTOR_POINTS_MULTIPLIER
            - $report->get_error_count('MissingProperty') * PROPERTY_POINTS_MULTIPLIER
            - $report->get_error_count('DifferentValue') * PROPERTY_VALUE_POINTS_MULTIPLIER;

        $percent = round($score / $maxScore * 100);
        if ($percent == 100) {
            $ocena = 6;
        } else if ($percent >= 90) {
            $ocena = 5;
        } else if ($percent >= 80) {
            $ocena = 4;
        } else if ($percent >= 70) {
            $ocena = 3;
        } else if ($percent >= 60) {
            $ocena = 2;
        } else {
            $ocena = 1;
        }

        echo '<h2>Wynik ' . $score . '/' . $maxScore . ' (' . $percent . '%), Ocena: ' . $ocena . ' </h2>';
        if (count($report->messages) > 0) {
            echo 'Błędy: <ul>';
            foreach ($report->messages as $msg) {
                echo '<li>' . $msg . '</li>';
            }
            echo '</ul>';
        }

        $newFile = !file_exists($GLOBALS['folder'] . '/wyniki.csv');
        $csv = fopen($GLOBALS['folder'] . '/wyniki.csv', 'at');
        if ($newFile) {
            // Dodaj nagłówek (zakładamy, że maksymalna ilość punktów nie zmienia się między odesłanymi pracami)
            fwrite($csv, '"Numer w dzienniku";"Nazwisko";"Imię";"Adres IP";"Punkty (max ' . $maxScore . ')";"Procenty";"Ocena"' . "\n");
        }
        fwrite($csv, $nr . ';"' . $nazwisko . '";"' . $imie . '";"' . $ip . '";' . $score . ';' . $percent . ';' . $ocena . "\n");

        if ($percent < 60) {
            echo '<h3>Plik został odrzucony</h3>';
        } else {
            move_uploaded_file($uploadedFile['tmp_name'], $GLOBALS['folder'] . '/' . $finalFileName);
        }
    }

    sprawdz_plik($_FILES['file']);
    ?>
</body>
</html>

