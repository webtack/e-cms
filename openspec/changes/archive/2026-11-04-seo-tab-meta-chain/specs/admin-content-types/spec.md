# Spec Delta

## MODIFIED Requirements

### Requirement: Вкладки «Відображення» і «SEO» типу
The section menu of a type SHALL contain the tab «Відображення» ("Display") (`/admin/{section}/{key}/display`, where `{section}` is `content-types`, `page-types` or `block-types`) for types of all kinds, between «Налаштування» ("Settings") and «Поля» ("Fields"). For types `kind = content` and `kind = page` the menu SHALL contain, in addition to «Налаштування», «Відображення», «Поля» and «Записи» ("Records"), the tab «SEO» (`/admin/content-types/{key}/seo` and `/admin/page-types/{key}/seo`, the fields `meta_title`, `meta_description`, `meta_image` of the type itself), which follows right after «Відображення»; types `block` have no tab «SEO». The tab «Відображення» of the type `content` SHALL have two blocks: the values of the section (the fields `section_title`, `section_description`, `section_cover`) and «Публічне відображення» ("Public display"); the tab «Відображення» of the types `page` and `block` SHALL have only the block «Публічне відображення» (they have no values of their own section). The tab «SEO» SHALL be a separate page with **one** form and one sticky panel «Скасувати / Зберегти» ("Cancel / Save"): the values `meta_*` of the type and the block «Мета-дані» (see the requirement «Блок «Мета-дані» на вкладці «SEO»» — "Block «Meta data» on the tab «SEO»") are saved in one atomic request. The settings page of the type (`/admin/{section}/{key}/edit`) SHALL contain only the main form of the type and the section «Список записів» ("List of records"), without value panels and without the block «Публічне відображення». Labels, hints (the description of the field) and length limits SHALL be taken from the definitions of the fields. The input element SHALL match the type of the field (short text, multiline text, formatted text, choice of an image from the media library). All values are optional; an empty value SHALL clear the saved one. The value tabs SHALL work only with the base language: there is no language switcher, the values are saved as base. Each tab SHALL have the same sticky action panel at the bottom of the window with «Скасувати» ("Cancel") and «Зберегти» ("Save"), as the rest of the admin forms; «Зберегти» submits the form of the tab. The tab «Відображення» SHALL have **one** shared button «Зберегти», which in one request saves both the values of the section (only for `content`) and `settings.public`; the saving SHALL be atomic: if any part fails validation, nothing is saved, and the errors are shown next to the corresponding fields (the values of the section — next to the fields, the public settings — in the block «Публічне відображення»). Saving the tab «Відображення» SHALL NOT change the values of the tab «SEO», and saving «SEO» SHALL NOT change the values of the section and the rest of `settings.public` (the block «Мета-дані» changes only `settings.public.meta`). The address `/admin/content-types/{key}/section` SHALL respond with 404. The address `/seo` for types `block` SHALL respond with 404.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of kind `content`
- **THEN** the menu of the type contains the tabs «Налаштування», «Відображення», «SEO», «Поля», «Записи», and the current one is active

#### Scenario: Menu of a page or block type
- **WHEN** the user opens any page of a type of kind `page` or `block`
- **THEN** the type `page` has the tabs «Налаштування», «Відображення», «SEO», «Поля», «Записи»; the type `block` — «Налаштування», «Відображення», «Поля», «Записи» without «SEO»

#### Scenario: Opening the settings page
- **WHEN** the user opens the settings of the type `news` of kind `content`
- **THEN** the page contains the main form of the type and the section «Список записів»; there are no value panels and no block «Публічне відображення» on it

#### Scenario: Opening the tab «Розділ» ("Section")
- **WHEN** the user opens the tab «Відображення» (formerly «Розділ») of the type `news` of kind `content`
- **THEN** they see the block of the section values with the current values of the base language, the block «Публічне відображення» and one action panel, and there are no SEO fields

#### Scenario: Opening the tab «Відображення» of a page type
- **WHEN** the user opens `/admin/page-types/home/display`
- **THEN** they see only the block «Публічне відображення» (the choice of the fields of the page) and the action panel

#### Scenario: Saving the values of the section
- **WHEN** the user fills `section_title` «Новини» ("News"), chooses `section_cover` from the media library and saves on the tab «Відображення»
- **THEN** the values are saved for the type, and after reloading the page they are shown

#### Scenario: Shared saving of the values of the section and the public settings
- **WHEN** the user changes `section_title` and the choice of the fields of the page and presses the single button «Зберегти»
- **THEN** both the values of the section and `settings.public` are saved, and after reloading both are shown

#### Scenario: An error in one part does not save the other
- **WHEN** the user changes `section_title` and enters a negative number of records per page
- **THEN** the saving is rejected, an error is shown next to the field of the number, and `section_title` remains the previous one

#### Scenario: Invalid value of the section
- **WHEN** the value of `section_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the value and `settings.public` remain the previous ones

#### Scenario: Sticky action panel on the tabs
- **WHEN** the user opens the tab «Відображення» or «SEO» in a window 400 px high (at 1280 and 390 px wide)
- **THEN** the panel with «Скасувати» and «Зберегти» is fully visible at the bottom of the window

#### Scenario: Saving SEO does not touch the section
- **WHEN** the user changes `meta_title` on the tab «SEO» and saves
- **THEN** the values `section_*` and `settings.public` of the type remain unchanged

#### Scenario: Clearing a value
- **WHEN** the user clears `meta_description` on the tab «SEO» and saves
- **THEN** the saved value is deleted

#### Scenario: Invalid value
- **WHEN** the value of `meta_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the values remain the previous ones

#### Scenario: Old address of the tab
- **WHEN** the user opens `/admin/content-types/news/section`
- **THEN** the response is «не знайдено» ("not found")

#### Scenario: SEO of a page type
- **WHEN** the user opens `/admin/page-types/home/seo`
- **THEN** they see the form of the values `meta_title`, `meta_description`, `meta_image` of the type with an action panel and the block «Мета-дані», and saving the values does not change the values of the records of this type

#### Scenario: Page or block types
- **WHEN** the user opens the settings of a type of kind `block`
- **THEN** there is no tab «SEO» in the menu, and the address `/admin/block-types/{key}/seo` responds with 404; the tab «Відображення» is present (the type `page` has the tab «SEO», see «SEO типу сторінки» — "SEO of a page type")

### Requirement: Секція «Публічне відображення» на вкладці «Відображення»
The tab «Відображення» of the type SHALL contain the block «Публічне відображення» for all kinds. For `block` and `page` it contains only the choice of the fields of the page; for `content` — also the fields of the card, the sorting (field + direction), the number of records per page and the switch of the paginator. The choice of meta fields (`settings.public.meta`) SHALL be absent on this tab: it lives on the tab «SEO». Only added fields (`is_system = false`) are offered for the choice; `title`, the meta fields and service attributes are not offered, because they are always available to the template. The block SHALL show the saved state with the default values and SHALL be saved together with the values of the section by the single button «Зберегти» of the tab, updating only the key `public` of the settings and not changing the saved `settings.public.meta`. The settings form of the type does NOT contain this block.

#### Scenario: Default values
- **WHEN** the tab «Відображення» of the type `content` without public settings is opened
- **THEN** the switch «Усі додані поля» ("All added fields") for the page is on, the card has no added fields, the sorting is «Дата публікації» ("Publication date") descending, the number is «0» (all)

#### Scenario: Explicit choice of the fields of the page
- **WHEN** the administrator turns off «Усі додані поля», marks `price` and saves
- **THEN** after reopening only `price` is marked, and a new field of the type does not appear among the marked ones

#### Scenario: Returning to «all»
- **WHEN** the administrator turns on «Усі додані поля» and saves
- **THEN** the key `page_fields` is not saved, and a new field automatically enters the page

#### Scenario: Other keys are not overwritten
- **WHEN** the tab is saved with changed public settings
- **THEN** `settings.list` and the other keys of `settings` remain unchanged

#### Scenario: Saving does not wipe the meta settings
- **WHEN** the type has `settings.public.meta`, and the administrator changes the choice of the fields of the page and saves the tab «Відображення»
- **THEN** `settings.public.meta` remains unchanged

#### Scenario: Choice of meta fields
- **WHEN** the administrator opens the tab «Відображення» of the type `content` or `page`
- **THEN** there is no choice of meta fields («Заголовок», «Опис», «Зображення») on it: it is on the tab «SEO»

#### Scenario: Permitted fields for each choice
- **WHEN** the save request of the tab «Відображення» contains `settings.public.meta`
- **THEN** the saving is rejected with a validation error, nothing is changed; the permitted fields for each choice are described in the requirement «Блок «Мета-дані» на вкладці «SEO»»

#### Scenario: Invalid field
- **WHEN** the request of the tab «Відображення» contains `settings.public.meta.image` with the key of a field of the type «рядок» ("string") or of a non-existent field
- **THEN** the saving is rejected, nothing is changed

#### Scenario: Meta data without a choice
- **WHEN** the administrator saves the tab «Відображення» of a type that has no `settings.public.meta`
- **THEN** the key `meta` does not appear in `settings.public`

#### Scenario: Type of block
- **WHEN** the administrator opens the tab «Відображення» of a type of kind `block`
- **THEN** there is no sub-block «Мета-дані», and a request with `settings.public.meta` for such a type is rejected

#### Scenario: Settings form without the public block
- **WHEN** the user opens the settings form of a type of any kind
- **THEN** there is no block «Публічне відображення» on it

## ADDED Requirements

### Requirement: Блок «Мета-дані» на вкладці «SEO»
The tab «SEO» of the types `content` and `page` SHALL contain, under the form of the values `meta_*` of the type, the block «Мета-дані», framed by a border as an independent section, but without a button of its own: it is part of the shared form of the tab and is saved by the single button «Зберегти» of the sticky panel together with the values `meta_*`. The block SHALL have three optional choices, each — with one item or «Не вибрано» ("Not selected"): «Заголовок» ("Heading") — the added fields of the type «рядок»; «Опис» ("Description") — the added fields of the types «текст» ("text") and «форматований текст» ("formatted text"); «Зображення» ("Image") — the added fields of the types «зображення» ("image") and «відео YouTube». The chosen key SHALL be saved in `settings.public.meta` (`title`, `description`, `image`); an unselected item is not saved. Saving the tab (`PUT /admin/content-types/{key}/values` and `/admin/page-types/{key}/values`) SHALL in one transaction write the values `meta_*` and change in the settings only `settings.public.meta`, without changing the other keys of `settings.public` and `settings`, keep the user on the tab and show a success message; an empty choice of all three SHALL remove the key `meta`, and an empty `public` — is not saved. An invalid field (a type outside the list, a non-existent or system one) SHALL be rejected with an error next to the block without changes to the type and without saving the values `meta_*`. For the types `block` there is no block, and the addresses `…/seo` and `…/values` respond with 404. The value is used by the public site as the metadata of the page (the requirement of the public site «Мета-дані сторінки…» — "Page metadata…").

#### Scenario: Block on the tab «SEO»
- **WHEN** the user opens the tab «SEO» of the type `news` (`content`) or of the type `home` (`page`)
- **THEN** under the form of the values `meta_*` they see a separate block «Мета-дані» with a frame and three choices «Заголовок», «Опис», «Зображення», without a button of its own, and at the bottom of the window one sticky panel «Скасувати / Зберегти»

#### Scenario: Choice of meta fields
- **WHEN** the administrator chooses the field `headline` for «Заголовок», `summary` for «Опис», `cover` for «Зображення» and saves the tab
- **THEN** after reopening the same fields are selected, and `settings.public.meta` has the keys `title`, `description`, `image`

#### Scenario: List of the choice «Заголовок»
- **WHEN** the administrator opens the choice «Заголовок»
- **THEN** they see «Не вибрано» and the added fields of the type «рядок», without the service item `title`

#### Scenario: Permitted fields for each choice
- **WHEN** the administrator opens the choice «Опис» or «Зображення»
- **THEN** «Опис» has only the fields «текст» and «форматований текст», «Зображення» — «зображення» and «відео YouTube»

#### Scenario: Invalid field
- **WHEN** the request saves `settings.public.meta.image` with the key of a field of the type «рядок», of a non-existent field, or the key `title`, or `settings.public.meta.title` with the value `title`
- **THEN** the saving is rejected with an error next to the block, nothing is changed

#### Scenario: The rest of the settings does not change
- **WHEN** the type has `settings.public.page_fields` and `settings.list`, and the administrator saves the tab
- **THEN** `settings.public.page_fields` and `settings.list` remain unchanged

#### Scenario: Meta data without a choice
- **WHEN** the administrator leaves all three choices «Не вибрано» and saves
- **THEN** the key `meta` is not saved in `settings.public`

#### Scenario: Shared saving of values and meta data
- **WHEN** the administrator changes `meta_title` of the type and the choice «Опис» in the block and presses the single button «Зберегти»
- **THEN** both `meta_title` and `settings.public.meta` are saved, and after reloading both are shown

#### Scenario: An error in one part does not save the other
- **WHEN** the request contains a correct value of `meta_title` and an invalid field in `settings.public.meta.image`
- **THEN** the saving is rejected, the error is shown next to the block, the value of `meta_title` and `settings.public.meta` remain the previous ones

#### Scenario: Type of block
- **WHEN** a save request `…/values` or opening `…/seo` arrives for a type of kind `block`
- **THEN** the response is 404
