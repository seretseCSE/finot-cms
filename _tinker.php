$cats = App\Models\CourseCategory::all();
echo $cats->count() . " categories\n";
foreach ($cats as $c) {
    echo "id={$c->id} name={$c->name} slug=" . ($c->slug ?: 'NULL') . " parent=" . ($c->parent_id ?: 'NULL') . "\n";
}
