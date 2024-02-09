# Narzędzie do porównywania CSS
Te proste narzędzie służy do wyszukiwania różnic w kodach CSS.

## Sposób użycia
Plik `src/checker.php` zawiera wszystkie potrzebne deklaracje, należy dołączyć go do projektu w którym ma zostać użyty wraz z wymaganą biblioteką [PHP-CSS-Parser](https://github.com/MyIntervals/PHP-CSS-Parser/). Domyślnie zakładamy, że biblioteka jest dostarczona przez [composer](https://getcomposer.org/) i dołączamy plik automatycznie przez niego wygenerowany.

Aby porównać dwa pliki CSS użyć trzeba funkcji `check_css` do której podajemy dwa argumenty:
 - Oczekiwany kod CSS
 - Kod CSS do sprawdzenia
W razie błędu funkcja zwraca typ `string`, w którym zawarty jest powód błędu. Po udanym porównaniu funkcja zwraca typ `DifferenceReport` w którym zawarte są
wszelkie różnice pomiędzy podanym kodem oraz suma punktów różnicy.

Przykładowe użycie ([plik `src/check_json.php`](https://github.com/patryk3211/CssChecker/blob/master/src/check_json.php)):
```php
<?php
// Zaimportuj plik z potrzebnymi funkcjami
require 'checker.php';

// Dekodowanie JSON otrzymanego od klienta
$json = json_decode(file_get_contents('php://input'));
$templateCss = $json->template;
$inputCss = $json->input;

// Porównaj otrzymany kod CSS
$report = check_css($templateCss, $inputCss);

// Wyślij wynik porównania do klienta w formacie JSON
header('Content-Type: text/json');
echo json_encode($report->messages);

```

## Ustawienia
Możliwa jest modyfikacja warunków wyszukiwania różnic w kodzie. Aby zmienić przyznawane punkty różnicy odnieś się do [kodu](https://github.com/patryk3211/CssChecker/blob/e2872a51d4f677a88030a5f128ed5d4d317cf116/src/checker.php#L41-L59)

Jest też możliwe definiowanie nowych procesorów właściwości CSS. Są to funkcje które służą do przekształcania złożonych właściwości CSS na kilka prostszych.

