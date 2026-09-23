<?php

namespace App\Services;

/**
 * MBR-100001, MBR-100002, … Sequential and never reused. Call it inside the
 * activation transaction so a rolled-back activation also releases the lock
 * without consuming a code.
 */
class MemberCodeGenerator
{
    public const SEQUENCE = 'member_code';

    public const PREFIX = 'MBR-';

    public function __construct(private SequenceGenerator $sequences) {}

    public function next(): string
    {
        return self::PREFIX.$this->sequences->next(self::SEQUENCE);
    }
}
