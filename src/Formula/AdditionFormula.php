<?php

namespace TokenFormula\Formula;

use TokenFormula\FormulaInterface;

/**
 * Calculate Sum.
 *
 */
class AdditionFormula implements FormulaInterface {
  /**
   * {@inheritdoc}
   */
  public function getCaller() {
    return 'S';
  }

  /**
   * {@inheritdoc}
   */
  public function calculate(array $values) {
    return array_sum($values);
  }
}
