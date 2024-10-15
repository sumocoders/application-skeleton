import { startStimulusApp } from '@symfony/stimulus-bundle';
import Clipboard from '@stimulus-components/clipboard';
import SidebarCollapsable from 'sumocoders/SidebarCollapsable';
import Toast from 'sumocoders/Toast';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('clipboard', Clipboard);
app.register('sidebar-collapsable', SidebarCollapsable);
app.register('toast', Toast);
