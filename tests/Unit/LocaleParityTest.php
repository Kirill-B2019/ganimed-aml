<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Tests\Unit;

use Tests\TestCase;

class LocaleParityTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function flatten(array $items, string $prefix = ''): array
    {
        $out = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += $this->flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_]\w*/', $text, $colon);
        preg_match_all('/\{[A-Za-z_]\w*\}/', $text, $braces);

        $all = array_merge($colon[0] ?? [], $braces[0] ?? []);
        sort($all);

        return $all;
    }

    /**
     * @dataProvider phpLangFiles
     */
    public function test_php_lang_files_have_matching_keys_and_placeholders(string $file): void
    {
        $en = $this->flatten(require base_path('lang/en/'.$file));
        $ru = $this->flatten(require base_path('lang/ru/'.$file));

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ru))), $file.' missing in RU');
        $this->assertSame([], array_values(array_diff(array_keys($ru), array_keys($en))), $file.' extra in RU');

        foreach ($en as $key => $text) {
            $this->assertSame(
                $this->placeholders($text),
                $this->placeholders($ru[$key]),
                $file.' placeholder mismatch: '.$key,
            );
        }
    }

    /**
     * @return list<array{0: string}>
     */
    public static function phpLangFiles(): array
    {
        return [
            ['aml.php'],
            ['auth.php'],
            ['pagination.php'],
            ['passwords.php'],
            ['validation.php'],
        ];
    }

    public function test_json_ui_strings_have_matching_keys(): void
    {
        $en = json_decode((string) file_get_contents(base_path('lang/en.json')), true);
        $ru = json_decode((string) file_get_contents(base_path('lang/ru.json')), true);

        $this->assertIsArray($en);
        $this->assertIsArray($ru);
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ru))), 'JSON missing in RU');
        $this->assertSame([], array_values(array_diff(array_keys($ru), array_keys($en))), 'JSON extra in RU');
    }

    public function test_russian_locale_resolves_validation_and_profile_copy(): void
    {
        app()->setLocale('ru');

        $this->assertSame('Поле адрес кошелька обязательно.', __('validation.required', ['attribute' => __('validation.attributes.address')]));
        $this->assertSame('Сохранить', __('Save'));
        $this->assertSame('Данные профиля', __('Profile Information'));
        $this->assertSame('Проверено вручную', __('aml.verdicts.manual'));
    }
}
