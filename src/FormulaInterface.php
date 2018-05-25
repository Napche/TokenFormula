<?php

namespace TokenFormula;


interface FormulaInterface
{
    public function getCaller();

    public function calculate(array $values);
}