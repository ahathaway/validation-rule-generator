<?php

namespace ahathaway\ValidationRuleGenerator\Types;


class BlobType
{
    use _Common;
    // use _Numeric;

    public $col;
    public $rules = [];

    public function __invoke($col)
    {
        $this->setCol($col);

        $this->nullable();

        return $this->rules;
    }

}
