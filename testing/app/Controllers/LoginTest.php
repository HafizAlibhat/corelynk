<?php

namespace App\Controllers;

use App\Models\UserModel;

class LoginTest extends BaseController
{
    public function index()
    {
        echo "<h1>Login Test Helper</h1>";
        
        $userModel = new UserModel();
        
        // Get all users
        $users = $userModel->findAll();
        
        echo "<h2>Available Users:</h2>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Role</th><th>Test Login</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['first_name'] . " " . $user['last_name'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td><a href='/logintest/testlogin/" . $user['id'] . "'>Auto Login</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<hr>";
        echo "<h2>Manual Login Test</h2>";
        echo "<p>The default password for all users is: <strong>password</strong></p>";
        echo "<p>Try logging in with:</p>";
        echo "<ul>";
        echo "<li>Email: <strong>admin@example.com</strong> | Password: <strong>password</strong></li>";
        echo "<li>Email: <strong>planner@example.com</strong> | Password: <strong>password</strong></li>";
        echo "</ul>";
        
        echo "<hr>";
        echo "<h2>Password Hash Test</h2>";
        $testPassword = "password";
        $testHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $isValid = password_verify($testPassword, $testHash);
        echo "<p>Testing password 'password' against hash: " . ($isValid ? "✅ VALID" : "❌ INVALID") . "</p>";
        
        echo "<hr>";
        echo "<p><a href='/auth/login'>Go to Login Page</a> | <a href='/batches'>Go to Batches</a></p>";
    }
    
    public function testLogin($userId)
    {
        $userModel = new UserModel();
        $user = $userModel->find($userId);
        
        if ($user) {
            // Set session manually
            $sessionData = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'logged_in' => true
            ];
            
            session()->set($sessionData);
            
            return redirect()->to('/batches')->with('success', 'Auto-logged in as ' . $user['first_name'] . ' ' . $user['last_name']);
        }
        
        return redirect()->to('/logintest')->with('error', 'User not found');
    }
}
