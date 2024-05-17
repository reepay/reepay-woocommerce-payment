import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import App from '@/admin/debug-page/App'

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.billwerk-debug-page').forEach((el) => {
        ReactDOM.createRoot(el!).render(
            <React.StrictMode>
                <App />
            </React.StrictMode>,
        )
    })
})
