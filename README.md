A helper class that can be used to inject attributes into an Eloquent model without having to modify the model.

This class cannot inject default values to models that weren't created by Eloquent (like using `new static`). But still can trigger the save event. You will have to handle this by yourself.

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