<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanScheme extends Model
{
    protected $table = 'loan_schemes';

    protected $fillable = ['bank_name', 'acronym_name', 'scheme_name'];

    public function memberLoans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberLoan::class, 'scheme_id');
    }

    public function shgLoans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Shgloan::class, 'scheme_id');
    }
}
