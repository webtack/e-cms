# admin-content-nodes Specification (delta)

## ADDED Requirements

### Requirement: Мініатюра `thumb` у комірці списку записів
Комірка поля типу `image` у таблиці записів SHALL показувати стиль `thumb` зображення (поле `preview` даних файла; якщо його немає — оригінал). Мініатюра SHALL бути посиланням на оригінал із `target="_blank"` і `rel="noopener noreferrer"` та SHALL мати `cursor-pointer`. Для множинного поля це стосується кожної мініатюри.

#### Scenario: Мініатюра в комірці
- **WHEN** запис має поле `image` із файлом
- **THEN** `src` зображення в комірці — адреса стилю `thumb`, а `href` посилання — адреса оригіналу

#### Scenario: Курсор
- **WHEN** користувач наводить курсор на мініатюру в комірці
- **THEN** курсор — вказівник (`cursor-pointer`)
