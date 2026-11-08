import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

test.describe('Spec 23 - Cashier Default Cash Account', () => {
    test('admin configures cashier default cash account, cashier pre-selects it, and falls back to first cash account when null', async ({ page }) => {
        // 1. Login as admin
        await page.goto('http://localhost/login');
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // 2. Visit school settings with default (null) setting
        await page.goto('http://localhost/settings/school');
        await expect(page.getByText('Akun kas default kasir')).toBeVisible();
        await expect(page.locator('#cashier_default_cash_account_id')).toHaveValue('');

        // 3. Visit Cashier with a student selected (setting=null -> pre-selects first active cash account: 1-1100 Kas Tunai, ID=1)
        await page.goto('http://localhost/fees/cashier?students[]=1');
        await expect(page.locator('#deposit_account_id')).toHaveValue('1');

        // 4. Update setting in School Profile to account ID 2 (1-1200 Bank)
        await page.goto('http://localhost/settings/school');
        await page.selectOption('#cashier_default_cash_account_id', '2');
        await page.getByRole('button', { name: 'Save School Profile' }).click();

        // Verify setting updated in database
        const dbCheck1 = execSync('./vendor/bin/sail artisan tinker --execute \'echo App\\Models\\SchoolSetting::first()->cashier_default_cash_account_id;\'').toString().trim();
        expect(dbCheck1).toBe('2');

        // 5. Visit Cashier (setting=2 -> pre-selects account ID 2)
        await page.goto('http://localhost/fees/cashier?students[]=1');
        await expect(page.locator('#deposit_account_id')).toHaveValue('2');

        // 6. Reset setting in School Profile to "" (Otomatis)
        await page.goto('http://localhost/settings/school');
        await page.selectOption('#cashier_default_cash_account_id', '');
        await page.getByRole('button', { name: 'Save School Profile' }).click();

        // Verify setting reset in database
        const dbCheck2 = execSync('./vendor/bin/sail artisan tinker --execute \'echo var_export(App\\Models\\SchoolSetting::first()->cashier_default_cash_account_id, true);\'').toString().trim();
        expect(dbCheck2).toBe('NULL');

        // 7. Visit Cashier (setting=null -> falls back to first active cash account, ID=1)
        await page.goto('http://localhost/fees/cashier?students[]=1');
        await expect(page.locator('#deposit_account_id')).toHaveValue('1');
    });
});
