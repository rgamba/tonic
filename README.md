# tonic
Fast and powerful PHP templating engine that compiles down to native PHP code.
## Usage
Using Tonic is pretty straight forward. 
```php
$tpl = new Tonic("demo.html");
$tpl->user_role = "member";
echo $tpl->render();
```
It's also very flexible. The above code can also be written like:
```php
$tpl = new Tonic();
echo $tpl->load("demo.html")->assign("user_role","member")->render();
```
## Show me the sintax
Using Tonic
```html
<body>
<h1>Bienvenido {$user.name.capitalize().truncate(50)}</h1>
Rol de usuario: {$role.lower().if("admin","administrator").capitalize()}
</body>
```
vs. writting all in PHP
```html
<body>
<h1>Bienvenido <?php echo (strlen($user["name"]) > 50 ? substr(ucwords($user["name"]),0,50)."..." : ucwords($user["name"])) ?></h1>
Rol de usuario: <?php if(strtolower($role) == "admin") { echo "Administrator" } else { echo ucwords($role) } ?>
</body>
```
## Caching
All tonic templates are compiled back to native PHP code. It's highly recommended that you use the caching functionality so that the same template doesn't need to be compiled over and over again increasing the CPU usage on server side.
```php
$tpl = new Tonic();
$tpl->cache_dir = "./cache/"; // Be sure this directory exists and has writing permissions
$tpl->enable_content_cache = true; // Importante to set this to true!
```
## Modifiers
Modifiers are functions that modify the output variable in various ways. All modifiers must be preceded by a variable and can be chained with other modifiers. Example:
```html
{$variable.modifier1().modifier2().modifier3()}
```
We can also use modifiers in the same way when using associative arrays:
```html
{$my_array.item.sub_item.modifier1().modifier2().modifier3()}
```
### List of modifiers

Name | Description
--- | ---
upper() | Uppercase
lower() | Lowercase
capitalize() | Capitalize words (ucwords)
abs() | Absolute value
truncate(len) | Truncate and add "..." if string is larger than "len"
count() | Alias to count()
length() | alias to count()
date(format) | Format date like date(format)
nl2br() | Alias to nl2br
stripSlashes() | Alias to stripSlashes()
sum(value) | Sums value to the current variable
substract(value) | Substracts value to the current variable
multiply(value) | Multiply values
divide(value) | Divide values
addSlashes() | Alias of addSlashes()
encodeTags() | Encode the htmls tags inside the variable
decodeTags() | Decode the tags inside the variable
stripTags() | Alias of strip_tags()
urldecode() | Alias of urldecode()
trim() | Alias of trim()
sha1() | Returns the sha1() of the variable
numberFormat(decimals) | Alias of number_format()
lastIndex() | Returns the last array's index of the variable
lastValue() | Returns the array's last element
jsonEncode() | Alias of json_encode()
replace(find,replace) | Alias of str_replace()
default(value) | In case variable is empty, assign it value
ifEmpty(value [,else_value]) | If variable is empty assign it value, else if else_value is set, set it to else_value
