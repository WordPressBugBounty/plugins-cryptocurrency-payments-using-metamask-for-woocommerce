import App from './WalletConnectors';
import { createRoot } from 'react-dom/client';
const container = document.getElementById('cmpw_meta_connect');
const root = createRoot(container); // createRoot(container!) if you use TypeScript
root.render(<App/>);

