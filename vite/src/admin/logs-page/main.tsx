import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import App from './App'

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.billwerk-logs').forEach((el) => {
        ReactDOM.createRoot(el!).render(
            <React.StrictMode>
                <App
                />
            </React.StrictMode>,
        )
    })
})
