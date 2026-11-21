<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    //名前が未入力の場合、バリデーションメッセージが表示される
    public function test_name_is_required(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    //メールアドレスがメールアドレスが未入力の場合、バリデーションメッセージが表示される
    public function test_email_is_required(): void
    {
        $response = $this->post('/register', [
            'name' => '田中太郎',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    //パスワードが8文字未満の場合、バリデーションメッセージが表示される
    public function test_password_must_be_at_least_8_characters(): void
    {
        $response = $this->post('/register', [
            'name' => '田中太郎',
            'email' => 'test@example.com',
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    //パスワードが一致しない場合、バリデーションメッセージが表示される
    public function test_password_confirmation_must_match(): void
    {
        $response = $this->post('/register', [
            'name' => '田中太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    //パスワードが未入力の場合、バリデーションメッセージが表示される
    public function test_password_is_required(): void
    {
        $response = $this->post('/register', [
            'name' => '田中太郎',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    //フォームに内容が入力されていた場合、データが正常に保存される
    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'name' => '田中太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => '田中太郎',
            'email' => 'test@example.com',
        ]);
    }
}
