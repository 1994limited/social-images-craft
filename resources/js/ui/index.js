/*
    What the shared pages import as '@social-images/ui': Craft's versions of
    the components and helpers each product supplies.
*/
export { default as Badge } from './Badge.vue';
export { default as Button } from './Button.vue';
export { default as Dropdown } from './Dropdown.vue';
export { default as DropdownItem } from './DropdownItem.vue';
export { default as DropdownMenu } from './DropdownMenu.vue';
export { default as DropdownSeparator } from './DropdownSeparator.vue';
export { default as Head } from './Head.vue';
export { default as Header } from './Header.vue';
export { default as Input } from './Input.vue';
export { default as Modal } from './Modal.vue';
export { default as Select } from './Select.vue';
export { default as Textarea } from './Textarea.vue';

export const router = {
    visit(url) {
        window.location.href = url;
    },
};
