export default class LanguageSwitcher {
    static #CSS_BLOCK = 'language-switcher';

    static get #CSS_ELEMENT_MENU_ITEM() {
        return `${this.#CSS_BLOCK}__language-menu-dropdown-item`;
    }

    #menuItems = [];

    constructor() {
        this.#menuItems = document.querySelectorAll(`.${LanguageSwitcher.#CSS_ELEMENT_MENU_ITEM}`);
    }

    addAnchor() {
        this.#menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                const anchor = window.location.hash;

                if (anchor) {
                    this.href = this.href.split('#')[0] + anchor;
                }
            })
        });
    }
}
