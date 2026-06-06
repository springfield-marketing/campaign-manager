<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateEnvironment extends Command
{
    protected $signature   = 'env:validate';
    protected $description = 'Verify that all required environment variables are set';

    /**
     * Required in every environment.
     */
    private array $required = [
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
    ];

    /**
     * Required only in production.
     */
    private array $requiredInProduction = [
        'REDIS_HOST',
        'REDIS_PORT',
        'REVERB_APP_ID',
        'REVERB_APP_KEY',
        'REVERB_APP_SECRET',
        'MAIL_FROM_ADDRESS',
    ];

    /**
     * Variables that must not retain their default placeholder values.
     */
    private array $mustNotBePlaceholder = [
        'APP_KEY'           => '',
        'MAIL_FROM_ADDRESS' => 'hello@example.com',
        'DB_PASSWORD'       => null,
    ];

    public function handle(): int
    {
        $errors = [];

        foreach ($this->required as $key) {
            if (empty(env($key))) {
                $errors[] = "$key is not set";
            }
        }

        if (app()->environment('production')) {
            foreach ($this->requiredInProduction as $key) {
                if (empty(env($key))) {
                    $errors[] = "$key is required in production but is not set";
                }
            }
        }

        foreach ($this->mustNotBePlaceholder as $key => $placeholder) {
            $value = env($key);
            if ($placeholder !== null && $value === $placeholder) {
                $errors[] = "$key still has its placeholder value \"$placeholder\"";
            }
        }

        if (app()->environment('production')) {
            if (env('APP_DEBUG') === 'true' || env('APP_DEBUG') === true) {
                $errors[] = 'APP_DEBUG must be false in production';
            }
            if (env('QUEUE_CONNECTION') === 'sync') {
                $errors[] = 'QUEUE_CONNECTION=sync is not suitable for production';
            }
            if (env('CACHE_STORE') === 'array') {
                $errors[] = 'CACHE_STORE=array is not suitable for production';
            }
            if (env('SESSION_DRIVER') === 'array') {
                $errors[] = 'SESSION_DRIVER=array is not suitable for production';
            }
        }

        if (empty($errors)) {
            $this->info('Environment validation passed.');
            return self::SUCCESS;
        }

        $this->error('Environment validation failed:');
        foreach ($errors as $error) {
            $this->line("  • $error");
        }

        return self::FAILURE;
    }
}
