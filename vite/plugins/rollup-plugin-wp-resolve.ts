import type { OutputOptions, InputOptions, ExternalOption } from 'rollup'

const ns = '@wordpress/'
const nsExclude = ['icons', 'interface']
const wordpressMatch = new RegExp(`^${ns}(?!(${nsExclude.join('|')})).*$`) // /^@wordpress\/(?!(icons|interface)).*$/

const external: Record<string, string> = {
    jquery: 'window.jQuery',
    'lodash-es': 'window.lodash',
    lodash: 'window.lodash',
    moment: 'window.moment',
    'react-dom': 'window.ReactDOM',
    react: 'window.React',
}

export default function wpResolve() {
    return {
        name: 'wp-resolve',
        options: (options: InputOptions) => {
            if (Array.isArray(options.external) === false) {
                // If string, RegExp or function
                options.external = [options.external].filter(
                    Boolean,
                ) as ExternalOption
            }

            if (Array.isArray(options.external)) {
                options.external = options.external.concat(
                    Object.keys(external),
                )
                options.external.push(wordpressMatch)
            }

            return options
        },
        outputOptions: (outputOptions: OutputOptions) => {
            const configGlobals = outputOptions.globals

            const resolveGlobals = (id: string) => {
                if (
                    typeof configGlobals === 'object' &&
                    id in configGlobals &&
                    configGlobals[id]
                ) {
                    return configGlobals[id]
                }

                // options.globals is a function - defer to it
                if (typeof configGlobals === 'function') {
                    const configGlobalId = configGlobals(id)

                    if (configGlobalId && configGlobalId !== id) {
                        return configGlobalId
                    }
                }

                // see if it's a static wp external
                if (id in external && external[id]) {
                    return external[id]
                }

                if (wordpressMatch.test(id)) {
                    // convert @namespace/component-name to namespace.componentName
                    return id
                        .replace(new RegExp(`^${ns}`), 'wp.')
                        .replace(/\//g, '.')
                        .replace(/-([a-z])/g, (_, letter) =>
                            letter.toUpperCase(),
                        )
                }

                return ''
            }

            outputOptions.globals = resolveGlobals
        },
    }
}
