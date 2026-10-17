import React from 'react';
import { createRoot } from 'react-dom/client';
import { Slide, ToastContainer, toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import './toast-theme.css';

/**
 * Mount the shared React Toastify container. Loaded on demand by `./toast`, so React and the Toastify
 * stylesheet stay out of the initial bundle (and off the render-blocking path) until a toast is shown.
 *
 * @returns {typeof toast} The Toastify API; toasts emitted right after mounting are queued until the container renders.
 */
export function mountToastContainer() {
    const host = document.createElement('div');
    host.id = 'drinkflow-toast-root';
    document.body.appendChild(host);
    createRoot(host).render(
        React.createElement(ToastContainer, {
            position: 'top-right',
            autoClose: 4000,
            hideProgressBar: false,
            newestOnTop: true,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
            transition: Slide,
            theme: 'light',
        }),
    );

    return toast;
}
