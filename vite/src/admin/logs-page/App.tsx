import React from 'react'
import { Provider } from 'react-redux'
import Logs from '@/admin/logs-page/components/Logs'
import { store } from '@/admin/logs-page/store'
import clsx from 'clsx'

const App: React.FC = () => {
    return (
        <Provider store={store}>
            <div className={clsx('bw-bg-white bw-p-4')}>
                <Logs></Logs>
            </div>
        </Provider>
    )
}

export default App
