<?php

namespace ahathaway\ValidationRuleGenerator\Types;


class StringType
{
    use _Common;
    use _Strings;

    public $col;
    public $rules = [];

    public function __invoke($col)
    {
        $this->setCol($col);

        $this->nullable();
        $this->length();

        return $this->rules;
    }

}
