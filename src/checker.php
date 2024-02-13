<?php
// Importowanie bibliotek
require __DIR__ . '/../vendor/autoload.php';

use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Parsing\SourceException;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

// Stwórz globalne ustawienia dla parserów CSS
$GLOBALS['CSS_CHECKER_PARSER_SETTINGS'] = Settings::create()->beStrict();
if(!isset($GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'])) {
  $GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'] = true;
}

class DifferenceReport {
  public int $differenceScore = 0;
  /**
   * @var array<int, string> $messages
   */
  public array $messages = [];

  public array $errorCount = [];

  public function add_difference(int $score, string $message, string $type): void {
    $this->differenceScore += $score;
    array_push($this->messages, $message);
    if(!isset($this->errorCount[$type])) {
      $this->errorCount[$type] = 0;
    }
    $this->errorCount[$type] += 1;
  }

  public function print_report(): void {
    echo 'Messages: <ul>';
    foreach($this->messages as $msg) {
      echo '<li>'.$msg.'</li>';
    }
    echo '</ul>';
    echo 'Error Counts: <ul>';
    foreach($this->errorCount as $key => $value) {
      echo '<li>'.$key.' = '.$value.'</li>';
    }
    echo '</ul>';
  }

  public function append_report(DifferenceReport $report): void {
    $this->differenceScore += $report->differenceScore;
    array_push($this->messages, ...$report->messages);
    foreach($report->errorCount as $key => $value) {
      if(!isset($this->errorCount[$key])) {
        $this->errorCount[$key] = 0;
      }
      $this->errorCount[$key] += $value;
    }
  }

  public function get_error_count(string $key): int {
    if(!isset($this->errorCount[$key])) {
      return 0;
    }
    return $this->errorCount[$key];
  }
}

/*
 * Tu zdefiniowane są punkty używane do wyszukiwania najbardziej prawidłowych definicji styli.
 *   SCORE_MISSING_PROPERTY => Punkty dodawane za właściwości zdefiniowane tylko we wzrocu
 *   SCORE_ADDITIONAL_PROPERTY => Punkty dodawane za właściwości tylko w kodzie do sprawdzenia
 *   SCORE_DIFFERENT_VALUE => Punkty dodawane za różne wartości właściwości
 *   SCORE_ADDITIONAL_SELECTOR => Punkty dodawane za selektor tylko w kodzie do sprawdzenia
 *   SCORE_MISSING_SELECTOR => Punkty dodawane za selektor zdefiniowany tylko we wzorcu
 * Wartość zero oznacza, że te różnice nie będą dodawane do wzorca.
 *
 * Uwaga! Jeżeli SCORE_DIFFERENT_VALUE będzie większe od SCORE_MISSING_PROPERTY 
 *        lub SCORE_ADDITIONAL_PROPERTY to możliwe są nieprawidłowe powiązania
 *        między selektorami wzorca oraz kodu do sprawdzenia.
 */
const SCORE_MISSING_PROPERTY = 20;
const SCORE_ADDITIONAL_PROPERTY = 10;
const SCORE_DIFFERENT_VALUE = 1;

const SCORE_ADDITIONAL_SELECTOR = 50;
const SCORE_MISSING_SELECTOR = 100;

class ElementNode {
  public bool $isBlockElement = false;
  public string $selector = '';

  /**
   * @var array<int, Rule> $rules
   */
  public array $rules = [];

  public function calculate_difference(ElementNode $other): DifferenceReport {
    $report = new DifferenceReport();

    $otherRules = $other->rules;
    foreach($this->rules as $rule) {
      $foundIndex = -1;
      $foundRule = null;
      for($i = 0; $i < count($otherRules); ++$i) {
        if($rule->getRule() == $otherRules[$i]->getRule()) {
          $foundRule = $otherRules[$i];
          $foundIndex = $i;
          break;
        }
      }
      if($foundRule == null) {
        // Punkty dla właściwości nieistniejących we wzrocu
        if(SCORE_ADDITIONAL_PROPERTY != 0 && !$GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'])
          $report->add_difference(SCORE_ADDITIONAL_PROPERTY, 'Element ('.$this->selector.') posiada dodatkową właściwość ('.$rule->getRule().')', 'AdditionalProperty');
      } else {
        if(strval($rule->getValue()) != strval($foundRule->getValue())) {
          // Punkty dla właściwości o innych wartościach
          if(SCORE_DIFFERENT_VALUE != 0)
            $report->add_difference(SCORE_DIFFERENT_VALUE, 'Właściwość elementu ('.$this->selector.') jest inna ('.$rule->getValue().') niż oczekiwana ('.$foundRule->getValue().')', 'DifferentValue');
        }
        // Usuń znalezioną właściwość
        array_splice($otherRules, $foundIndex, 1);
      }
    }

    if(SCORE_MISSING_PROPERTY != 0) {
      foreach($otherRules as $missingRule) {
        $report->add_difference(SCORE_MISSING_PROPERTY, 'W elemencie ('.$this->selector.') brakuje właściwości zdefiniowanej we wzrocu ('.$missingRule->getRule().')', 'MissingProperty');
      }
    }
    return $report;
  }
}

class ElementTracker {
  public array $elements = [];

  public function add_rules(string $selector, array $rules): void {
    foreach($this->elements as $element) {
      if($element->selector == $selector) {
        foreach($rules as $rule) {
          array_push($element->rules, $rule);
        }
        return;
      }
    }
    $element = new ElementNode();
    $element->selector = $selector;
    if($selector[0] == '#' || $selector[0] == '.') {
      $element->isBlockElement = true;
    }
    foreach($rules as $rule) {
      array_push($element->rules, $rule);
    }
    array_push($this->elements, $element);
  }

  /**
   * Zakładamy, że $other to wzór kodu.
   */
  public function compare(ElementTracker $other): DifferenceReport {
    $differenceReport = new DifferenceReport();
  
    $otherElements = $other->elements;
    foreach($this->elements as $element) {
      if($element->isBlockElement) {
        // Porównujemy każdy element bloku z każdym innym elementem bloku aby móc ignorować ich nazwy
        // Po wyszukaniu pary usuwamy ten element z listy dostępnych
        $selectedIndex = -1;
        $selectedElement = null;
        $bestReport = null;
        for($i = 0; $i < count($otherElements); ++$i) {
          if(!$otherElements[$i]->isBlockElement)
            continue;
          $report = $element->calculate_difference($otherElements[$i]);
          if($bestReport == null || $report->differenceScore < $bestReport->differenceScore) {
            $selectedElement = $otherElements[$i];
            $bestReport = $report;
            $selectedIndex = $i;
          }
        }
        if($selectedElement == null) {
          // Nie znaleziono selektora blokowego we wzrocu
          if(SCORE_ADDITIONAL_SELECTOR != 0 && !$GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'])
            $differenceReport->add_difference(SCORE_ADDITIONAL_SELECTOR, 'Dodatkowy selektor styli ('.$element->selector.') w kodzie', 'AdditionalSelector');
        } else {
          $differenceReport->append_report($bestReport);
          array_splice($otherElements, $selectedIndex, 1);
        }
      } else {
        // Wyszukujemy odpowiadający selektor elementu
        $selectedElement = null;
        $selectedIndex = -1;
        for($i = 0; $i < count($otherElements); ++$i) {
          if($otherElements[$i]->selector == $element->selector) {
            $selectedElement = $otherElements[$i];
            $selectedIndex = $i;
            break;
          }
        }
        if($selectedElement == null) {
          if(SCORE_ADDITIONAL_SELECTOR != 0 && !$GLOBALS['CSS_CHECKER_ALLOW_ADDITIONAL_PROPERTIES'])
            $differenceReport->add_difference(SCORE_ADDITIONAL_SELECTOR, 'Dodatkowy selektor styli ('.$element->selector.') w kodzie', 'AdditionalSelector');
        } else {
          $report = $element->calculate_difference($selectedElement);
          $differenceReport->append_report($report);
          array_splice($otherElements, $selectedIndex, 1);
        }
      }
    }

    // Pozostałe selektory zostają uznane jako brakujące w kodzie
    if(SCORE_MISSING_SELECTOR != 0) {
      foreach($otherElements as $missingElement) {
        $differenceReport->add_difference(SCORE_MISSING_SELECTOR, 'Brakujący selektor styli ('.$missingElement->selector.')', 'MissingSelector');
      }
    }

    return $differenceReport;
  }

  public function display(): void {
    foreach($this->elements as $element) {
      echo "<h2>".$element->selector."</h2><ul>";
      foreach($element->rules as $rule) {
        echo "<li>".$rule."</li>";
      }
      echo "</ul>";
    }
  }
}

function expand_4_sides(Rule $inputRule) {
  // Rozbija jedną właściwość na cztery używając dopisków strony (left, right, top, bottom).
  $ruleName = $inputRule->getRule();
  $ruleValue = $inputRule->getValue();
  $ruleSuffix = '';

  // 0 => top, 1 => right, 2 => bottom, 3 => left
  $ruleValues = [];

  if($ruleValue instanceof Size) {
    // Wszystkie strony mają te same wartości
    $ruleValues = [ $ruleValue, $ruleValue, $ruleValue, $ruleValue ];
  } else if($ruleValue instanceof RuleValueList) {
    $valueComponents = $ruleValue->getListComponents();
    $valueCount = count($valueComponents);
    if($valueCount == 2) {
      // Przypisz wartości góra-dół i lewa-prawa
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[0], $valueComponents[1] ];
    } else if($valueCount == 3) {
      // Przypisz wartości góra, lewa-prawa, dół
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[2], $valueComponents[1] ];
    } else if($valueCount == 4) {
      // Przypisz wartości góra, prawa, dół, lewa
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[2], $valueComponents[3] ];
    } else {
      throw new LengthException('CSS property has more than the 4 expected values.');
    }
  } else {
    throw new UnexpectedValueException('CSS property value has an unexpected type.');
  }
  // Stwórz nowe właściwości z wartości podanej właściwości
  $outputRuleArray = [];

  $separatorPos = strpos($ruleName, '-');
  if($separatorPos !== false) {
    // Podziel nazwę właściwości
    $ruleSuffix = substr($ruleName, $separatorPos);
    $ruleName = substr($ruleName, 0, $separatorPos);
  }

  $rule = new Rule($ruleName.'-top'.$ruleSuffix);
  $rule->setValue($ruleValues[0]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-right'.$ruleSuffix);
  $rule->setValue($ruleValues[1]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-bottom'.$ruleSuffix);
  $rule->setValue($ruleValues[2]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-left'.$ruleSuffix);
  $rule->setValue($ruleValues[3]);
  array_push($outputRuleArray, $rule);

  return $outputRuleArray;
}

function expand_4_corners(Rule $inputRule) {
  // Rozbija jedną właściwość na cztery używając dopisków rogów (top-left, top-right, bottom-right, bottom-left).
  $ruleName = $inputRule->getRule();
  $ruleValue = $inputRule->getValue();
  $ruleSuffix = '';

  // 0 => top-left, 1 => top-right, 2 => bottom-right, 3 => bottom-left
  $ruleValues = [];

  if($ruleValue instanceof Size) {
    // Wszystkie strony mają te same wartości
    $ruleValues = [ $ruleValue, $ruleValue, $ruleValue, $ruleValue ];
  } else if($ruleValue instanceof RuleValueList) {
    $valueComponents = $ruleValue->getListComponents();
    $valueCount = count($valueComponents);
    if($valueCount == 2) {
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[0], $valueComponents[1] ];
    } else if($valueCount == 3) {
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[2], $valueComponents[1] ];
    } else if($valueCount == 4) {
      $ruleValues = [ $valueComponents[0], $valueComponents[1], $valueComponents[2], $valueComponents[3] ];
    } else {
      throw new LengthException('CSS property has more than the 4 expected values.');
    }
  } else {
    throw new UnexpectedValueException('CSS property value has an unexpected type.');
  }
  // Stwórz nowe właściwości z wartości podanej właściwości
  $outputRuleArray = [];

  $separatorPos = strpos($ruleName, '-');
  if($separatorPos !== false) {
    // Podziel nazwę właściwości
    $ruleSuffix = substr($ruleName, $separatorPos);
    $ruleName = substr($ruleName, 0, $separatorPos);
  }

  $rule = new Rule($ruleName.'-top-left'.$ruleSuffix);
  $rule->setValue($ruleValues[0]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-top-right'.$ruleSuffix);
  $rule->setValue($ruleValues[1]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-bottom-right'.$ruleSuffix);
  $rule->setValue($ruleValues[2]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-bottom-left'.$ruleSuffix);
  $rule->setValue($ruleValues[3]);
  array_push($outputRuleArray, $rule);

  return $outputRuleArray;
}

// Stwórz tablice procesorów dla typowo używanych właściwości
$GLOBALS['CSS_CHECKER_RULE_PROCESSORS'] = [
  'margin' => 'expand_4_sides',
  'padding' => 'expand_4_sides',
  'border-width' => 'expand_4_sides',
  'border-style' => 'expand_4_sides',
  'border-color' => 'expand_4_sides',
  'border-radius' => 'expand_4_corners',
];

function call_rule_processor(Rule $rule) {
  if(!key_exists($rule->getRule(), $GLOBALS['CSS_CHECKER_RULE_PROCESSORS'])) {
    // Domyślnie dodajemy zasade do rezultatu.
    return [ $rule ];
  }

  $callback = $GLOBALS['CSS_CHECKER_RULE_PROCESSORS'][$rule->getRule()];
  if(is_string($callback)) {
    return call_user_func($callback, $rule);
  } else if(is_array($callback)) {
    return call_user_func($callback[0], $rule, ...$callback[1]);
  }
}

function convert_css(string $cssText): ElementTracker {
  $templateParser = new Parser($cssText, $GLOBALS['CSS_CHECKER_PARSER_SETTINGS']);
  $templateDoc = $templateParser->parse();
  $template = new ElementTracker();

  foreach($templateDoc->getAllDeclarationBlocks() as $block) {
    $processedRules = [];
    foreach($block->getRules() as $rule) {
      $resultRules = call_rule_processor($rule, $processedRules);
      array_push($processedRules, ...$resultRules);
    }
    foreach($block->getSelectors() as $selector) {
      $template->add_rules($selector, $processedRules);
    }
  }

  return $template;
}

function check_css(string|ElementTracker $template, string|ElementTracker $css): DifferenceReport|string {
  try {
    if(is_string($template)) {
      $template = convert_css($template);
    }
    try {
      if(is_string($css)) {
        $css = convert_css($css);
      }
      $report = $css->compare($template);
      return $report;
    } catch(SourceException $e) {
      return '<h2>Błąd w podanym kodzie</h2>'.$e->getMessage();
    } catch(Exception $e) {
      return '<h2>Błąd podczas interpretacji kodu</h2>'.$e->getMessage();
    }
  } catch(SourceException $e) {
    return '<h2>Błąd we wzorze styli</h2>'.$e->getMessage();
  } catch(Exception $e) {
    return '<h2>Błąd podczas interpretacji wzoru</h2>'.$e->getMessage();
  }
}

// check_css(file_get_contents('template.css'), file_get_contents('test.css'));

