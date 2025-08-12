export default class RepeatedPasswordType {
    #wrapperElement;
    #passwordRequirementsElement;
    #togglerActionInfoElement;

    init() {
        this.#wrapperElement = document.querySelector('.js-repeated-password-wrapper');
        this.#togglerActionInfoElement
            = this.#wrapperElement.querySelector('.js-toggler-action-info');
        this.#passwordRequirementsElement = document.getElementById('passwordRequirements');

        this.#passwordRequirementsElement.addEventListener('show.bs.collapse', this.#onCollapse);
        this.#passwordRequirementsElement.addEventListener('hide.bs.collapse', this.#onCollapse);
    }

    #onCollapse = e => {
        this.#togglerActionInfoElement.textContent = (e.type === 'hide.bs.collapse') ? 'Show' : 'Hide';
    }
}
