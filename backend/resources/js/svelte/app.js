import { mount } from 'svelte';
import App from './App.svelte';

const target = document.getElementById('ornaments-world-app');

if (target) {
    mount(App, { target });
}
