# HOROSHOP XML Generator
<b>Скрипт для вивантаження даних з сайтів на Опенкарт в універсальному XML форматі HOROSHOP</b>
<br><br>
<b>Задача:</b><br>
Стандартний YML файл не розрахований на багатомовність і всі спроби приклеїти другу мову "скотчем та гвіздками" виглядають жалюгідно.
<b>Рішення:</b><br>
Тому було розроблено <b>універсальний XML формат HoroshopXML</b>, який:
<ul>
<li>Гарно підтримує багатомовність (by design)</li>
<li>Підтримує бідь-які мови (а не тільки російську і українську)</li>
<li>Не обмежений в кількості мов (можна вивантажувати 3х та більше мовні сайти</li>
<li>Підтримує не тільки базові дані категорій а і розширені, зокрема meta теги title, description та seoURL</li>
<li>Підтримує не тільки базові дані товарів а і розширені, зокрема meta теги title, description всі види артикулів, тощо</li>
<li>Оптимальний для переносу товарів з сайтів на Opencart до платформи Horoshop</li>

<b>Особливості скрипту:</b>
<ul>
<li>Підтримує Opencart 2.x, 3 і можливо 1.5 (не тестувався)</li>
<li>Вивантажує не тільки базові дані категорій а і розширені, зокрема meta теги title, description та seoURL</li>
<li>Вивантажує не тільки базові дані товарів а і розширені, зокрема meta теги title, description всі види артикулів, тощо</li>
<li>Автоматично вивантажує не стандартні данні, які додаються до товарів додатковими модулями< /li>
<li>Не потребує встановлення чи зміни файлів системи</li>
</ul>
<br><br>
<b>Використання:</b><br>
Скрипт потрібно розмістити в корені сайту Opencart. <br>Налаштування підключення до бази даних підхопиться автоматично.
Далі відкрити скрипт за адресою: https://sitename.com.ua/HSHOP_Export_1.php
<br>
Увага! В цілях безпеки звертання до скрипту без додаткових параметрів заборонено. Треба як мінімум параметр XML_KEY
В базовому скрипті перевірка його значення не відбувається. Лише наявність. Але в Production системі рекомендуємо додати перевірку 
на конкретне значення, щоб обмежити доступ до ваших товарів не авторизованим користувачам.

Самий простий варіант звернення до скрипта:

https://site.com.ua/HSHOP_Export_1.php?XML_KEY=

В даному скрипті є деяка кількість параметрів. Вони описані нижче:
<ul>
<li>        x_limit (число) - обмеження кількості товарів для виводу. За умовчання лише 10. Використовується для відладки. Щоб вивантажити всі товари задайте значення x_limit=0</li>
<li>        x_cat_limit (число) - обмеження кількості категорій для виводу. За умовчанням 0 (тобто виводяться всі категорії). Використовується для відладки. Щоб вивантажити всі товари задайте значення x_cat_limit=0</li>
<li>        x_simplecat (0,1) - вивести категорії в спрощеному форматі в одну строку (стандартний YML формат категорій)</li>
<li>        x_lang (число) - вивести лише одну певну мову. Треба явно вказати її ID</li>
<li>        x_pretty (0,1) - гарно форматувати XML. За умовчанням включено. Якщо вимкнути (поставити 0), то скрипт виведе данні в одну строку і відпрацює трішки швидше. Може бути корисним, якщо хостинг "не витягує"</li>
<li>        x_baseurl - адреса сайту в форматі: "sitename.com.ua" - потрібно для формування коректних абсолютних посилань, якщо скрипт запущено з консолі</li>
<li>        x_product_custom:", //todo</li>
<li>        x_product_description_custom (0,1) - виводити "самопальні" багатомовні поля для товарів. Якщо 1 - то виводиться все, що тільки напхали сторонні модулі.</li>
<li>        x_product_id (число) - вивести данні лише одного товару з id=x_product_id. Корисно для відладки.</li>
<li>        x_ocver - (2,3) версія опенкарт 2 чи 3.</li>
<li>        x_fix_utf - автоматично виправляти помилки кодування UTF (биті символи).</li>
<li>        x_show_empty_aliases = 1; //В разі відсутності alias в базі виводити типу index.php?route=product/category&path=ID</li>
<li>     private $x_quantity_status = 0; //Статус товару avaliable=true/false брати не з поля status а з кількості (якщо quantity > 0, то true)</li>

</ul>

Для спрощення налаштування, скрипт містить міні веб-адмінку, що доступна за посиланням:

https://site.com.ua/HSHOP_Export_1.php?web_admin&XML_KEY=
<br><br>
<b>Запуск з консолі:</b>
Для сайтів з великою кількістю товарів або на слабкому хостингу вивантажити всі товари за один раз може бути складним або неможливим.
Якщо збільшення таймаутів в налаштуваннях PHP не допомагає, або неможливе - даний скрипт можна запускати по ssh з тими ж параметрами.
Приклад використання:
php HSHOP_Export_1.php --x_limit=1 --x_cat_limit=1 --x_ocver=2 > horoshop_export.xml
<br><br>
<b>Example screenshots:</b>

![horoshop_catalog](https://user-images.githubusercontent.com/315178/165104364-3e7a77c2-ea68-4d10-9060-89351ffa8d08.png)
<br><b>..........</b><br><br>
