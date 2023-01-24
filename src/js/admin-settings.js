import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import SystemContext from './SystemContext';
import { columns, defaultPosts } from './Utils/ContextData';

const root = ReactDOM.createRoot( document.getElementById( 'media_root' ) );
/*
  - Columns is a simple array right now, but it will contain some logic later on. It is recommended by react-table to memoize the columns data
  - Here in this example, we have grouped our columns into two headers. react-table is flexible enough to create grouped table headers
*/

root.render(
    <SystemContext.Provider value={ { columns, defaultPosts } }>
        <App />
    </SystemContext.Provider>
);
