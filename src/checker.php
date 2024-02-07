<?php
// Importowanie bibliotek
require __DIR__ . '/../vendor/autoload.php';
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Parsing\SourceException;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

// Stwórz globalne ustawienia dla parserów CSS
$GLOBALS['settings'] = Settings::create()->beStrict();

class ElementNode {
  public string $selector;
  public RuleSet $rules;
}

function expand_4_sides(Rule $inputRule, bool $sideMiddle) {
  // Rozbija jedną właściwość na cztery używając dopisków strony (left, right, top, bottom).
  $ruleName = $inputRule->getRule();
  $ruleValue = $inputRule->getValue();

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

  $rule = new Rule($ruleName.'-top');
  $rule->setValue($ruleValues[0]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-right');
  $rule->setValue($ruleValues[1]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-bottom');
  $rule->setValue($ruleValues[2]);
  array_push($outputRuleArray, $rule);

  $rule = new Rule($ruleName.'-left');
  $rule->setValue($ruleValues[3]);
  array_push($outputRuleArray, $rule);

  return $outputRuleArray;
}

// Stwórz tablice procesorów dla typowo używanych właściwości
$GLOBALS['ruleProcessors'] = [
  'margin' => 'expand_4_sides',
  'padding' => 'expand_4_sides',
  'border-width' => 'expand_4_sides',
  'border-style' => 'expand_4_sides',
  'border-color' => 'expand_4_sides',
];

function call_rule_processor(Rule $rule) {
  if(!key_exists($rule->getRule(), $GLOBALS['ruleProcessors'])) {
    // Domyślnie dodajemy zasade do rezultatu.
    return [ $rule ];
  }

  $callback = $GLOBALS['ruleProcessors'][$rule->getRule()];
  if(is_string($callback)) {
    return call_user_func($callback, $rule);
  } else if(is_array($callback)) {
    return call_user_func($callback[0], $rule, ...$callback[1]);
  }
}

function check_css(string $templateText, string $cssText) {
  try {
    $templateParser = new Parser($templateText, $GLOBALS['settings']);
    $templateDoc = $templateParser->parse();

    foreach($templateDoc->getAllDeclarationBlocks() as $block) {
      foreach($block->getSelectors() as $selector) {
        echo '<h1>'.$selector.'</h1>';
      }
      $processedRules = [];
      foreach($block->getRules() as $rule) {
        $resultRules = call_rule_processor($rule, $processedRules);
        array_push($processedRules, ...$resultRules);
      }
      echo "<ul>";
      foreach($processedRules as $rule) {
        echo "<li>".$rule."</li>";
      }
      echo "</ul>";
      // var_dump($processedRules);
      echo '<hr />';
    }
  } catch(SourceException $e) {
    echo '<h2>Błąd we wzorze styli</h2>';
    echo $e->getMessage();
    die();
  } catch(Exception $e) {
    echo '<h2>Błąd podczas interpretacji wzoru</h2>';
    echo $e->getMessage();
    die();
  }
}

check_css(file_get_contents('template.css'), '');

