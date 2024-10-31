A helper class that can be used to inject attributes into an Eloquent model without having to modify the model.

Example
```php
(new InjectModelAttribute(User::class))
    ->attribute('money', function(User $user) use ($economy) {
        return $economy->getMoney($user);
    }, function (User $user, $name, $value) use ($economy) {
        $originalMoney = $economy->getMoney($user);
        $delta = $value - $originalMoney;
        $economy->addMoney($user, $delta);
    });

// Then you can do...
$money = $user->money;
$user->money = $money + 100;
```