# tonic
Super fast and powerful template engine.

## Usage
Using Tonic is pretty straight forward.
```php
use Tonic\Tonic;
$tpl = new Tonic("demo.html");
$tpl->user_role = "member";
echo $tpl->render();
```
It's also very flexible. The above code can also be written like:
```php
$tpl = new Tonic();
echo $tpl->load("demo.html")->assign("user_role","member")->render();
```
## Show me the syntax
Using Tonic
```html
<body>
<h1>Welcome {$user.name.capitalize().truncate(50)}</h1>
User role: {$role.lower().if("admin","administrator").capitalize()}
</body>
```
vs. writting all in PHP
```html
<body>
<h1>Welcome <?php echo (strlen($user["name"]) > 50 ? substr(ucwords($user["name"]),0,50)."..." : ucwords($user["name"])) ?></h1>
User role: <?php if(strtolower($role) == "admin") { echo "Administrator" } else { echo ucwords($role) } ?>
</body>
```
## Installation
Install using composer
```
$ composer require rgamba/tonic
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
## Working with dates
It's easy to handle and format dates inside a Tonic template.
```php
Tonic::$local_tz = 'America/New_york'; // Optionaly set the user's local tz
$tpl = new Tonic();
$tpl->my_date = date_create();
```
And the template
```html
<p>Today is {$my_date.date("Y-m-d h:i a")}</p>
```
Working with timezones
```html
<p>The local date is {$my_date.toLocal().date("Y-m-d h:i a")}</p>
```
Which will render `$my_date` to the timezone configured in ` Tonic::$local_tz`
### Custom timezone
```html
<p>The local date is {$my_date.toTz("America/Mexico_city").date("Y-m-d h:i a")}</p>
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
if(value, then_value [,else_value [,comparisson_operator]] ) | Conditionally set the variable's value. All arguments can be variables
preventTagEncode() | If ESCAPE_TAGS_IN_VARS = true, this prevents the variable's value to be encoded

### Creating custom modifiers
If you need a custom modifier you can extend the list and create your own.
```php
// This function will only prepend and append some text to the variable
Tonic::extendModifier("myFunction",function($input, $prepend, $append = ""){
    // $input will hold the current variable value, it's mandatory that your lambda
    // function has an input receiver, all other arguments are optional
    // We can perform input validations
    if(empty($prepend)) {
        throw new \Exception("prepend is required");
    }
    return $prepend . $input . $append;
});
```
And you can easily use this modifier:
```html
<p>{$name.myFunction("hello "," goodbye")}</p>
```
### Anonymous modifiers
Sometimes you just need to call functions directly from inside the template whose return value is constantly changing and therefore it can't be linked to a static variable. Also, it's value is not dependant on any variable. In those cases you can use anonymous modifiers.
To do that, you need to create a custom modifier, IGNORE the `$input` parameter in case you need to use other parameters.
```php
Tonic::extendModifier("imagesDir", function($input){
    // Note that $input will always be empty when called this modifier anonymously
    return "/var/www/" . $_SESSION["theme"] . "/images";
});
```
Then you can call it directly from the template
```html
<img src="{$.imagesDir()}/pic.jpg" />
```
## Context awareness
Tonic prevents you from escaping variables in your app that could led to possible attacks. Each variable that's going to be displayed to the user should be carefully escaped, and it sould be done acoardingly to it's context.
For example, a variable in a href attr of a link should be escaped in a different way from some variable in a javascript tag or a `<h1>` tag.
The good news is that tonic does all this work for you.
```php
$tonic->assign("array",array("Name" => "Ricardo", "LastName", "Gamba"));
$tonic->assign("ilegal_js","javascript: alert('Hello');");
```
And the HTML
```html
<a href="{$ilegal_js}">Click me</a>
<!-- Will render: <a href="javascript%3A+alert%28%27Hello%27%29%3B">Click me</a> -->
<p>The ilegal js is: {$ilegal_js}</p>
<!-- Will render: <p> The ilegal js is: javascript: alert(&#039;Hello&#039;);</p> -->
<a href="?{$array}">Valid link generated</a>
<!-- Will render: <a href="?Name=Ricardo&LastName=Gamba">Valid link generated</a> -->
<p> We can also ignore the context awareness: {$ilegal_js.ignoreContext()}</p>
```
## Include templates
You can include a template inside another template
```html
{include footer.html}
```
We can also fetch and external page and load it into our current template
```html
{include http://mypage.com/static.html}
```
Templates includes support nested calls, but beware that infinite loop can happen in including a template "A" inside "A" template.
## Control structures
### If / else
Making conditionals is very easy
```html
{if $user.role eq "admin"}
<h1>Hello admin</h1>
{elseif $user.role.upper() eq "MEMBER" or $user.role.upper() eq "CLIENT"}
<h1>Hello member</h1>
{else}
<h1>Hello guest</h1>
{endif}
```
You can use regular logic operators (==, !=, >, <, >=, <=, ||, &&) or you can use the following

Operator | Equivalent
--- | ---
eq | ==
neq | !=
gt | >
lt | <
gte | >=
lte | <=

### Loops
```html
<ul>
{loop $user in $users}
<li>{$user.name.capitalize()}</h1>
{endloop}
</ul>
```
Or if the array key is needed
```html
<ul>
{loop $i,$user in $users}
<li>{$i} - {$user.name.capitalize()}</h1>
{endloop}
</ul>
```
### Working with macros
Both if structures and loop constructs can be written in a more HTML-friendly way so your code can be more readable. Here's an example:
```html
<ul tn-if="$users">
    <li tn-loop="$user in $users">Hello {$user}</li>
</ul>
```
Which is exactly the same as:
```html
{if $users}
<ul>
    {loop $user in $users}
    <li>Hello {$user}</li>
    {endloop}
</ul>
{endif}
```
## Changelog
* 25-03-2015 - 3.0.0 - Added Context Awareness and Maco Syntax for ifs and loops
* 23-03-2015 - 2.2.0 - Added namespace support and added modifier exceptions
* 20-03-2015 - 2.1.0 - Added the option to extend modifiers.
* 19-03-2015 - 2.0.0 - IMPORTANT update. The syntax of most structures has changed slightly, it's not backwards compatible with previous versions.
