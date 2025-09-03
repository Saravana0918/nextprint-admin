// database/seeders/DecorationAreaSeeder.php
use App\Models\DecorationAreaTemplate;

public function run(): void {
  $rows = [
    ['A3','regular',297,420],
    ['A4','regular',210,297],
    ['A5','regular',148,210],
    ['A6','regular',105,148],
    ['A7','regular',74,105],
    ['A8','regular',52,74],
  ];
  foreach ($rows as [$n,$c,$w,$h]) {
    DecorationAreaTemplate::firstOrCreate(
      ['name'=>$n,'category'=>$c], ['width_mm'=>$w,'height_mm'=>$h]
    );
  }
}
