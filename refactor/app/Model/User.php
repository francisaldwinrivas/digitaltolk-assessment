<?php

namespace DTApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
// ... so on

class User extends Authenticatable
{
    // ... other methods and declarations here

    /**
     * Returns true if user has Admin user_type
     * 
     * @return boolean
     */
    public function isAdmin(): boolean
    {
        return $this->user_type === config('user.roles.admin');
    }

    /**
     * Returns true if user has Superadmin user_type
     * 
     * @return boolean
     */
    public function isSuperAdmin(): boolean
    {
        return $this->user_type === config('user.roles.superadmin');
    }

    /**
     * Returns true if user has Customer user_type
     * 
     * @return boolean
     */
    public function isCustomer(): boolean
    {
        return $this->user_type === config('user.roles.customer');
    }

    /**
     * Returns true if user has Translator user_type
     * 
     * @return boolean
     */
    public function isTranslator(): boolean
    {
        return $this->user_type === config('user.roles.translator');
    }
}