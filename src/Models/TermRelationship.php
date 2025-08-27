<?php

namespace Boukjijtarik\WooSales\Models;

class TermRelationship extends BaseWpModel
{
    protected string $baseTable = '{prefix}term_relationships';
    protected $primaryKey = 'object_id';
    public $incrementing = false;
}


