<?php
/*
 * Copyright (c) Portland Web Design, Inc 2023.
 */

namespace ahathaway\ValidationRuleGenerator\Types;


/**
 * Class BigIntType
 */
class BigIntType
{
    use _Common;
    use _Numeric;

    /**
     * @var
     */
    public $col;
    /**
     * @var array
     */
    public $rules = [];

    /**
     * @param $col
     * @return array
     */
    public function __invoke($col)
    {
        $this->setCol($col);

        $this->nullable();
        $this->numeric();
        $this->unsignedMin();

        return $this->rules;
    }

    /**
     * @return void
     */
    protected function unsignedMin()
    {
        if ($this->col->getUnsigned())
            $this->rules['min'] = 0;
    }

}
