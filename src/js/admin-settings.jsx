import React from 'react';
import ReactDOM from 'react-dom/client';
import App from '@/js/Component/App';
import { StateProvider } from '@/js/Utils/StateProvider';
import reducer, { initialState } from '@/js/Utils/reducer';
import {setupNavigation} from "@/js/navigation";
import '@/css/index.css';
import '@/scss/admin-settings.scss';

const root = ReactDOM.createRoot( document.getElementById( 'media_root' ) );

/*
  - Columns is a simple array right now, but it will contain some logic later on. It is recommended by react-table to memoize the columns data
  - Here in this example, we have grouped our columns into two headers. react-table is flexible enough to create grouped table headers
*/


// Render
root.render(
    <StateProvider reducer={reducer} initialState={initialState}>
        <App />
    </StateProvider>
);

//Row Js
setupNavigation();