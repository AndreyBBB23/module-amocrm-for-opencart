<?php
// Heading
$_['heading_title']                     = ' Интеграция с Amo.CRM';
$_['heading_title_raw']                 = 'Интеграция с Amo.CRM';

//Tabs
$_['tab_general']                       = 'Параметры';
$_['tab_header']                        = 'Шапка';
$_['tab_logs']                          = 'Логи';
$_['tab_support']                       = 'Поддержка';
$_['tab_license']                       = 'Лицензия';
$_['tab_useful']                        = 'Полезные ссылки';
$_['tab_handbooks']                     = 'Справочники';
$_['tab_contact_fields']                = 'Поля покупателя';
$_['tab_order_statuses']                = 'Статусы заказов';
$_['tab_tasks']                         = 'Создание задачи';
$_['tab_leads']                         = 'Сделки';
$_['tab_form_statuses']                 = 'Статусы формы';

// Text
$_['text_module_version']               = '3.x';
$_['text_success']                      = 'Настройки модуля обновлены!';
$_['text_module']                       = 'Модули';
$_['text_success_clear']                = 'Лог файл успешно очищен!';
$_['text_clear_log']                    = 'Очистить лог';
$_['text_clear']                        = 'Очистить';
$_['text_image_manager']                = 'Менеджер изображений';
$_['text_browse']                       = 'Обзор';
$_['text_update_success']               = 'Справочники обновлены';
$_['text_users']                        = 'Пользователи';
$_['text_fields']                       = 'Поля';
$_['text_pipelines']                    = 'Воронки + стадии';
$_['text_records']                      = 'Записей';
$_['text_name']                         = 'Название';
$_['text_refresh_handbooks']            = 'Обновить справочники';
$_['text_task_title']                   = 'Получен новый заказ {order_id}. Перезвонить для уточнения деталей.';

// Buttons
$_['button_save']                       = 'Сохранить';
$_['button_save_and_close']             = 'Сохранить и Закрыть';
$_['button_close']                      = 'Закрыть';
$_['button_recheck']                    = 'Проверить еще раз';
$_['button_clear_log']                  = 'Очистить лог';
$_['button_download_log']               = 'Скачать файл логов';

// Entry
$_['entry_debug']                       = 'Отладочный режим:<br /><span class="help">В логи модуля будет писаться различная информация для разработчика модуля.</span>';
$_['entry_status']                      = 'Статус:';
$_['entry_instruction']                 = 'Инструкция к модулю:';
$_['entry_history']                     = 'История изменений:';
$_['entry_faq']                         = 'Часто задаваемые вопросы:';
$_['entry_integration_id']              = "ID интеграции";
$_['entry_integration_secret']          = "Секретный ключ";
$_['entry_auth_code']                   = "Код авторизации";
$_['entry_amo_subdomain']               = 'Субдомен в системе';
$_['entry_amo_subdomain_desc']          = 'Можно посмотреть в адресной строке, имеет вид https://xxxx.amocrm.ru/ где хххх - субдомен';
$_['entry_contact_email_field']         = 'Поле e-mail';
$_['entry_contact_email_field_desc']    = 'Необходимо выбрать поле, которое на стороне ЦРМ обозначает "e-mail" контакта покупателя. Если нужного поля нет в списке необходимо создать его в ЦРМ и обновить справочники.';
$_['entry_contact_phone_field']         = 'Поле телефон';
$_['entry_contact_phone_field_desc']    = 'Необходимо выбрать поле, которое на стороне ЦРМ обозначает "телефон" контакта покупателя. Если нужного поля нет в списке необходимо создать его в ЦРМ и обновить справочники.';
$_['entry_create_task']                 = 'Создавать задачи при поступлении нового заказа';
$_['entry_task_user']                   = 'Ответственный сотрудник';
$_['entry_task_title']                  = 'Заголовок задачи';
$_['entry_task_title_desc']             = 'В заголовке задачи можно использовать {order_id} - будет заменено на номер заказа.';
$_['entry_contact_responsible']         = 'Ответсвенный сотрудник';
$_['entry_contact_responsible_desc']    = 'Выберите пользователя, который будет помечен как "Ответсвенный / Создавший контакт"';
$_['entry_lead_responsible']            = 'Ответсвенный сотрудник';
$_['entry_lead_title']                  = 'Название сделки';
$_['entry_lead_title_desc']             = 'В заголовке сделки можно использовать {order_id} - будет заменено на номер заказа.';
$_['entry_deleted_status']              = 'Статус удаленного заказа';
$_['entry_deleted_status_desc']         = 'Укажите статус, при котором сделка будет удалена из ЦРМ.';
$_['entry_webhook']                     = "Webhook URL";
$_['entry_webhook_desc']                = "Урл для создания WebHook на отслеживание изменения статусов сделок со стороны ЦРМ";
$_['entry_form_statuses']                = 'Выберите статус';
$_['entry_form_statuses_desc']           = 'Форма с данными будет отправлена в выбранный статус воронки';

// Error
$_['error_handbooks']       = 'Необходимо обновить справочники';
$_['error_permission']      = 'У Вас нет прав для управления этим модулем!';
$_['error_download_logs']   = 'Файл логов пустой или отсутствует!';
$_['error_ioncube_missing'] = '';
$_['error_license_missing'] = '';

// Links
$_['text_module_version']   = '3.x';
$_['error_ioncube_missing'] =   '<h3 style="color:red">Отсутствует IonCube Loader!</h3>
                                <p>Чтобы пользоваться нашим модулем, вам нужно установить IonCube Loader.</p>
                                <p>Для установки обратитесь в ТП Вашего хостинга</p>';