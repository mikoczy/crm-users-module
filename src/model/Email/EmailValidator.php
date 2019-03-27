<?php

namespace Crm\UsersModule\Email;

class EmailValidator
{
    /** @var ValidatorInterface[] */
    private $validators = [];

    private $lastValidator = null;

    public function isValid($email): bool
    {
        $this->lastValidator = null;
        foreach ($this->validators as $validator) {
            if (!$validator->isValid($email)) {
                $this->lastValidator = $validator;
                return false;
            }
        }
        return true;
    }

    public function addValidator(ValidatorInterface $validator)
    {
        $this->validators[] = $validator;
        return $this;
    }

    public function lastValidator()
    {
        return $this->lastValidator;
    }
}
