import esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

const defaultOptions = {
    bundle: true,
    platform: 'neutral',
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    target: ['es2020'],
    minify: !isDev,
    plugins: [
        {
            name: 'logPlugin',
            setup: function (build) {
                build.onStart(() => {
                    console.log(
                        `Build started at ${new Date().toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                    )
                })

                build.onEnd((result) => {
                    if (result.errors.length > 0) {
                        console.log(
                            `Build failed at ${new Date().toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                            result.errors,
                        )
                    } else {
                        console.log(
                            `Build finished at ${new Date().toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                        )
                    }
                })
            },
        },
    ],
}

compile({
    ...defaultOptions,
    entryPoints: ['./resources/js/index.js'],
    outfile: './resources/dist/filament-shortcut-keys.js',
})
