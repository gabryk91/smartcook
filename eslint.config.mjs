import nextcloud from '@nextcloud/eslint-config'

export default [
    ...nextcloud,
    {
        ignores: ['js/**', 'css/**', 'node_modules/**'],
    },
]
