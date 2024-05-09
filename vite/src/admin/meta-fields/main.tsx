import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import App from './App'
import { isWpPostType, WpPostTypeEnum } from '@/types/WpPost'

document.addEventListener('DOMContentLoaded', () => {
    const postIdElement: HTMLInputElement | null =
        document.querySelector('#post_ID')
    const userIdElement: HTMLInputElement | null =
        document.querySelector('#user_id')
    const postId = postIdElement ? Number(postIdElement.value) : null
    const userId = userIdElement ? Number(userIdElement.value) : null

    if (!postId && !userId) {
        return
    }

    document.querySelectorAll('.billwerk-meta-fields').forEach((el) => {
        const postType = el.getAttribute('data-post-type')
        let entityId = postId

        if (!postType || !isWpPostType(postType)) {
            return
        }

        if (postType === WpPostTypeEnum.User) {
            entityId = userId
        }

        if (!entityId) return

        ReactDOM.createRoot(el!).render(
            <React.StrictMode>
                <App
                    entityId={entityId}
                    postType={postType}
                />
            </React.StrictMode>,
        )
    })
})
