import js from '@eslint/js'
import globals from 'globals'
import eslintConfigPrettier from 'eslint-config-prettier'
import vue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import vueParser from 'vue-eslint-parser'

export default tseslint.config(
    {
        ignores: [
            'node_modules/**',
            'public/**',
            'vendor/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    ...tseslint.configs.recommended,
    {
        files: ['**/*.{ts,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            parser: tseslint.parser,
            sourceType: 'module',
        },
    },
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 'latest',
                extraFileExtensions: ['.vue'],
                parser: tseslint.parser,
                sourceType: 'module',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
    eslintConfigPrettier,
)
