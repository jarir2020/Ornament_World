import { mount } from 'svelte';
import App from './App.svelte';

const target = document.getElementById('ornaments-world-app');

function readPageProps(element) {
    try {
        return JSON.parse(element.dataset.props || '{}');
    } catch (error) {
        console.error('Unable to read storefront props.', error);
        return {};
    }
}

if (target) {
    mount(App, {
        target,
        props: { page: readPageProps(target) },
    });
}
