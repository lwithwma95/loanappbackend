<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string|null $mobile_number
 * @property string|null $aadhaar_number
 * @property string|null $date_of_birth
 * @property string|null $password
 * @property string|null $role
 * @property string      $status   pending|active|inactive
 * @property int|null    $district_id
 * @property int|null    $block_id
 */
#[Fillable(['name', 'email', 'mobile_number', 'aadhaar_number', 'date_of_birth', 'password', 'role', 'status', 'district_id', 'block_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
        ];
    }

    public function blockCadre(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BlockCadre::class, 'user_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }
}