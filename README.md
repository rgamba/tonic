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
