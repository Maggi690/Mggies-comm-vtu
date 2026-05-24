<?php

namespace Database\Seeders;

use App\Models\DataPlan;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedAdminUser();
        $this->seedProviders();
        $this->seedDataPlans();
        $this->seedSettings();
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.wallet.credit', 'users.wallet.debit',
            // Transactions
            'transactions.view', 'transactions.refund', 'transactions.retry', 'transactions.export',
            // Providers
            'providers.view', 'providers.create', 'providers.edit', 'providers.delete', 'providers.toggle',
            // Reports
            'reports.view', 'reports.export',
            // Support
            'tickets.view', 'tickets.reply', 'tickets.close',
            // Settings
            'settings.view', 'settings.edit',
            // Blacklist
            'blacklist.view', 'blacklist.manage',
            // Data Plans
            'data_plans.view', 'data_plans.edit',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        $roles = [
            'admin'            => $permissions,
            'assistant_admin'  => array_filter($permissions, fn($p) => !in_array($p, ['users.delete', 'providers.delete', 'settings.edit'])),
            'customer_support' => ['users.view', 'transactions.view', 'transactions.refund', 'tickets.view', 'tickets.reply', 'tickets.close'],
            'user'             => [],
            'agent'            => [],
            'vendor'           => [],
            'sub_reseller'     => [],
            'api_user'         => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->syncPermissions($rolePermissions);
        }

        $this->command->info('Roles and permissions seeded.');
    }

    private function seedAdminUser(): void
    {
        $admin = User::firstOrCreate(
            ['email' => config('app.admin_email', 'admin@universalvtupro.com')],
            [
                'ulid'       => Str::ulid(),
                'first_name' => 'Super',
                'last_name'  => 'Admin',
                'phone'      => '08000000001',
                'username'   => 'superadmin',
                'password'   => Hash::make(config('app.admin_password', 'Admin@12345!')),
                'user_type'  => 'admin',
                'status'     => 'active',
                'referral_code' => 'ADMIN001',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        // Create wallet for admin
        if (!$admin->wallet) {
            \App\Models\Wallet::create([
                'user_id'  => $admin->id,
                'ulid'     => Str::ulid(),
                'balance'  => 0,
                'ledger_balance' => 0,
                'frozen_balance' => 0,
                'currency' => 'NGN',
                'status'   => 'active',
            ]);
        }

        $this->command->info("Admin seeded: {$admin->email}");
    }

    private function seedProviders(): void
    {
        $providers = [
            [
                'name'        => 'VTpass',
                'slug'        => 'vtpass',
                'api_key'     => 'PLACEHOLDER_API_KEY',
                'secret_key'  => 'PLACEHOLDER_SECRET_KEY',
                'endpoint'    => 'https://vtpass.com/api',
                'services'    => ['airtime', 'data', 'cable', 'electricity', 'exam'],
                'priority'    => 1,
                'status'      => 'active',
            ],
            [
                'name'        => 'Husmodata',
                'slug'        => 'husmodata',
                'api_key'     => 'PLACEHOLDER_API_KEY',
                'secret_key'  => 'PLACEHOLDER_SECRET_KEY',
                'endpoint'    => 'https://husmodata.com/api',
                'services'    => ['airtime', 'data'],
                'priority'    => 2,
                'status'      => 'inactive',
            ],
            [
                'name'        => 'Gsubz',
                'slug'        => 'gsubz',
                'api_key'     => 'PLACEHOLDER_API_KEY',
                'secret_key'  => 'PLACEHOLDER_SECRET_KEY',
                'endpoint'    => 'https://gsubz.com/api',
                'services'    => ['airtime', 'data'],
                'priority'    => 3,
                'status'      => 'inactive',
            ],
        ];

        foreach ($providers as $data) {
            $provider = Provider::firstOrCreate(['slug' => $data['slug']], $data);

            // Seed provider services
            $networks = ['mtn', 'airtel', 'glo', '9mobile'];
            foreach ($data['services'] as $service) {
                foreach ($networks as $network) {
                    \App\Models\ProviderService::firstOrCreate([
                        'provider_id'  => $provider->id,
                        'service_type' => $service,
                        'network'      => $network,
                    ], [
                        'fee_type'     => 'flat',
                        'fee_value'    => 0,
                        'status'       => 'active',
                    ]);
                }
            }
        }

        $this->command->info('Providers seeded.');
    }

    private function seedDataPlans(): void
    {
        $provider = Provider::where('slug', 'vtpass')->first();

        $plans = [
            // MTN SME
            ['network' => 'mtn', 'plan_type' => 'sme',  'name' => '1GB SME',   'size' => 1,    'amount' => 270,   'selling_price' => 285,   'validity' => 30,  'provider_plan_id' => 'mtn-data-1000'],
            ['network' => 'mtn', 'plan_type' => 'sme',  'name' => '2GB SME',   'size' => 2,    'amount' => 490,   'selling_price' => 510,   'validity' => 30,  'provider_plan_id' => 'mtn-data-2000'],
            ['network' => 'mtn', 'plan_type' => 'sme',  'name' => '5GB SME',   'size' => 5,    'amount' => 1350,  'selling_price' => 1380,  'validity' => 30,  'provider_plan_id' => 'mtn-data-5000'],
            ['network' => 'mtn', 'plan_type' => 'sme',  'name' => '10GB SME',  'size' => 10,   'amount' => 2700,  'selling_price' => 2750,  'validity' => 30,  'provider_plan_id' => 'mtn-data-10000'],
            // MTN CG
            ['network' => 'mtn', 'plan_type' => 'cg',   'name' => '500MB CG',  'size' => 0.5,  'amount' => 150,   'selling_price' => 160,   'validity' => 30,  'provider_plan_id' => 'mtn-data-500'],
            ['network' => 'mtn', 'plan_type' => 'cg',   'name' => '1GB CG',    'size' => 1,    'amount' => 300,   'selling_price' => 315,   'validity' => 30,  'provider_plan_id' => 'mtn-data-1000-cg'],
            ['network' => 'mtn', 'plan_type' => 'cg',   'name' => '3GB CG',    'size' => 3,    'amount' => 900,   'selling_price' => 930,   'validity' => 30,  'provider_plan_id' => 'mtn-data-3000-cg'],
            // Airtel
            ['network' => 'airtel', 'plan_type' => 'cg', 'name' => '1GB',      'size' => 1,    'amount' => 300,   'selling_price' => 320,   'validity' => 30,  'provider_plan_id' => 'airtel-data-1000'],
            ['network' => 'airtel', 'plan_type' => 'cg', 'name' => '2GB',      'size' => 2,    'amount' => 500,   'selling_price' => 525,   'validity' => 30,  'provider_plan_id' => 'airtel-data-2000'],
            ['network' => 'airtel', 'plan_type' => 'cg', 'name' => '5GB',      'size' => 5,    'amount' => 1500,  'selling_price' => 1540,  'validity' => 30,  'provider_plan_id' => 'airtel-data-5000'],
            // GLO
            ['network' => 'glo',    'plan_type' => 'cg', 'name' => '1GB',      'size' => 1,    'amount' => 300,   'selling_price' => 320,   'validity' => 30,  'provider_plan_id' => 'glo-data-1000'],
            ['network' => 'glo',    'plan_type' => 'cg', 'name' => '2GB',      'size' => 2,    'amount' => 500,   'selling_price' => 525,   'validity' => 30,  'provider_plan_id' => 'glo-data-2000'],
            // 9Mobile
            ['network' => '9mobile','plan_type' => 'cg', 'name' => '1GB',      'size' => 1,    'amount' => 300,   'selling_price' => 320,   'validity' => 30,  'provider_plan_id' => '9mobile-data-1000'],
            ['network' => '9mobile','plan_type' => 'cg', 'name' => '2.5GB',    'size' => 2.5,  'amount' => 500,   'selling_price' => 525,   'validity' => 30,  'provider_plan_id' => '9mobile-data-2500'],
        ];

        foreach ($plans as $plan) {
            DataPlan::firstOrCreate(
                ['provider_plan_id' => $plan['provider_plan_id']],
                array_merge($plan, [
                    'provider_id'   => $provider?->id,
                    'size_unit'     => 'GB',
                    'validity_unit' => 'days',
                    'status'        => 'active',
                ])
            );
        }

        $this->command->info('Data plans seeded.');
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'site_name',          'value' => 'Universal VTU Pro',  'group' => 'general'],
            ['key' => 'support_email',       'value' => 'support@universalvtupro.com', 'group' => 'general'],
            ['key' => 'min_fund_amount',     'value' => '100',               'group' => 'wallet'],
            ['key' => 'max_fund_amount',     'value' => '5000000',           'group' => 'wallet'],
            ['key' => 'referral_bonus',      'value' => '100',               'group' => 'referral'],
            ['key' => 'referral_enabled',    'value' => 'true',              'group' => 'referral'],
            ['key' => 'maintenance_mode',    'value' => 'false',             'group' => 'general'],
            ['key' => 'airtime_enabled',     'value' => 'true',              'group' => 'services'],
            ['key' => 'data_enabled',        'value' => 'true',              'group' => 'services'],
            ['key' => 'cable_enabled',       'value' => 'true',              'group' => 'services'],
            ['key' => 'electricity_enabled', 'value' => 'true',              'group' => 'services'],
            ['key' => 'exam_enabled',        'value' => 'true',              'group' => 'services'],
        ];

        foreach ($settings as $setting) {
            \DB::table('settings')->updateOrInsert(['key' => $setting['key']], $setting);
        }

        $this->command->info('Settings seeded.');
    }
}
