import { __ } from '@wordpress/i18n'
import { useCallback, useState } from 'react'
import CodeMirror from '@uiw/react-codemirror'
import { php } from '@codemirror/lang-php'
import { githubDark } from '@uiw/codemirror-theme-github'
import { DebugApi } from '@/api/wp/debug'
import { AxiosError } from 'axios'

const App = () => {
    const [value, setValue] = useState('<?php')
    const [result, setResult] = useState<string | null>(null)

    const onChange = useCallback((val: string) => {
        setValue(val)
    }, [])

    const run = async () => {
        setResult(null)
        try {
            const response = await DebugApi.run(value)
            setResult(response.message)
        } catch (e) {
            if (e instanceof AxiosError) {
                setResult(e.response?.data.message)
            }
        }
    }

    return (
        <>
            <CodeMirror
                value={value}
                height={'500px'}
                theme={githubDark}
                extensions={[php({})]}
                onChange={onChange}
            />
            <button
                type={'button'}
                onClick={() => run()}
                className={'button bw-mt-2'}
            >
                {__('Run', 'reepay-checkout-gateway')}
            </button>
            {result && (
                <div
                    className={'bw-mt-2'}
                    dangerouslySetInnerHTML={{ __html: result }}
                ></div>
            )}
        </>
    )
}

export default App
