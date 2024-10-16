import { startStimulusApp } from '@symfony/stimulus-bundle';
import Clipboard from 'sumocoders/Clipboard';
import SidebarCollapsable from 'sumocoders/SidebarCollapsable';
import Toast from 'sumocoders/Toast';
import Theme from 'sumocoders/Theme';
import Tooltip from 'sumocoders/Tooltip';
import Popover from 'sumocoders/Popover';
import DateTimePicker from 'sumocoders/DateTimePicker';
import Tabs from 'sumocoders/Tabs';
import PasswordStrengthChecker from 'sumocoders/PasswordStrengthChecker';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('clipboard', Clipboard);
app.register('sidebar-collapsable', SidebarCollapsable);
app.register('toast', Toast);
app.register('theme', Theme);
app.register('tooltip', Tooltip);
app.register('popover', Popover);
app.register('date-time-picker', DateTimePicker);
app.register('tabs', Tabs);
app.register('password-strength-checker', PasswordStrengthChecker);
