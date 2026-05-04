<?php

namespace App\Controllers;

class TestSystem extends BaseController
{
    public function index()
    {
        // Set test user session
        session()->set([
            'user_id' => 1,
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'first_name' => 'System',
            'last_name' => 'Administrator'
        ]);

        $data = [
            'page_title' => 'System Test - All Features',
            'current_page' => 'test',
            'session_info' => session()->get(),
            'test_links' => [
                'Dashboard' => base_url(),
                'Batches Management' => base_url('/batches'),
                'Batch Creation' => base_url('/batches/create'),
                'PDF Test' => base_url('/pdfs/batchReport/1'),
                'Gate Passes' => base_url('/gatepasses'),
                'Database Test' => base_url('/databasetest')
            ]
        ];

        return view('test_system', $data);
    }

    public function setTestSession()
    {
        session()->set([
            'user_id' => 1,
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'first_name' => 'System',
            'last_name' => 'Administrator'
        ]);

        return redirect()->to('/batches')->with('success', 'Test session created. You can now access all features.');
    }
}
