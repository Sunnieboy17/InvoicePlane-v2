<?php
use Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;

$u = User::where('email', 'owner@invoiceplane.com')->first();
if (!$u) {
    $u = new User();
    $u->name = 'Owner';
    $u->email = 'owner@invoiceplane.com';
    $u->password = Hash::make('password');
    $u->save();
    echo "User created.\n";
} else {
    $u->password = Hash::make('password');
    $u->save();
    echo "User updated.\n";
}
