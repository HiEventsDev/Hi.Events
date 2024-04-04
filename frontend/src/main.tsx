import ReactDOM from 'react-dom/client';
import { App } from './App.tsx';
import { queryClient } from './utilites/queryClient.ts';


ReactDOM.createRoot(document.getElementById('app') as HTMLElement).render(
   <App queryClient={queryClient} />
);
