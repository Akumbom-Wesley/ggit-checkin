import './bootstrap';
import Alpine from 'alpinejs';
import checkin from './checkin';
import addParticipant from './add-participant';

window.Alpine = Alpine;

// Merge the add-participant form into the check-in screen (keeps getters intact).
Alpine.data('checkin', (config) => {
    const component = checkin(config);
    Object.defineProperties(component, Object.getOwnPropertyDescriptors(addParticipant()));
    return component;
});

Alpine.start();