<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rule. Feel free to tweak each of these messages here.
    |
    */
    'accepted' => ':attribute ተቀባይነት ማግኘት አለበት።',
    'accepted_if' => ':other :value ሲሆን :attribute ተቀባይነት ማግኘት አለበት።',
    'active_url' => ':attribute ትክክለኛ URL አይደለም።',
    'after' => ':attribute ከ :date በኋላ መሆን አለበት።',
    'after_or_equal' => ':attribute ከ :date ጋር እኩል ወይም ከዚያ በኋላ መሆን አለበት።',
    'alpha' => ':attribute ፊደላትን ብቻ የያዘ መሆን አለበት።',
    'alpha_dash' => ':attribute ፊደላትን፣ ቁጥሮችን፣ ሰረዞችን እና የምር ሰረዞችን (underscores) ብቻ የያዘ መሆን አለበት።',
    'alpha_num' => ':attribute ፊደላትን እና ቁጥሮችን ብቻ የያዘ መሆን አለበት።',
    'array' => ':attribute ዝርዝር (array) መሆን አለበት።',
    'ascii' => ':attribute ASCII ፊደላትን እና ምልክቶችን ብቻ የያዘ መሆን አለበት።',
    'before' => ':attribute ከ :date በፊት መሆን አለበት።',
    'before_or_equal' => ':attribute ከ :date ጋር እኩል ወይም ከዚያ በፊት መሆን አለበት።',
    'between' => [
        'numeric' => ':attribute በ :min እና በ :max መካከል መሆን አለበት።',
        'file' => ':attribute መጠኑ በ :min እና በ :max ኪሎባይት መካከል መሆን አለበት።',
        'string' => ':attribute በ :min እና በ :max ፊደላት መካከል መሆን አለበት።',
        'array' => ':attribute በ :min እና በ :max መካከል ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'boolean' => ':attribute እውነት (true) ወይም ሀሰት (false) መሆን አለበት።',
    'can' => ':attribute ያልተፈቀደ ዋጋ ይዟል።',
    'confirmed' => 'የ :attribute ማረጋገጫ አይመሳሰልም።',
    'current_password' => 'የይለፍ ቃሉ ትክክል አይደለም።',
    'date' => ':attribute ትክክለኛ ቀን አይደለም።',
    'date_equals' => ':attribute ከ :date ጋር እኩል የሆነ ቀን መሆን አለበት።',
    'date_format' => ':attribute ከ :format ቅርጸት ጋር አይመሳሰልም።',
    'decimal' => ':attribute :decimal የአስርዮሽ ቦታዎች ሊኖሩት ይገባል።',
    'declined' => ':attribute ውድቅ መደረግ አለበት።',
    'declined_if' => ':other :value ሲሆን :attribute ውድቅ መደረግ አለበት።',
    'different' => ':attribute እና :other የተለያዩ መሆን አለባቸው።',
    'digits' => ':attribute :digits አሃዝ መሆን አለበት።',
    'digits_between' => ':attribute በ :min እና በ :max መካከል አሃዞች ሊኖሩት ይገባል።',
    'dimensions' => ':attribute የተሳሳተ የምስል መጠን አለው።',
    'distinct' => ':attribute የተደገመ ዋጋ አለው።',
    'doesnt_end_with' => ':attribute በሚከተሉት ማለቅ የለበትም: :values።',
    'doesnt_start_with' => ':attribute በሚከተሉት መጀመር የለበትም: :values።',
    'email' => ':attribute ትክክለኛ የኢሜይል አድራሻ መሆን አለበት።',
    'ends_with' => ':attribute በሚከተሉት አንዱ ማለቅ አለበት: :values።',
    'enum' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'exists' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'file' => ':attribute ፋይል መሆን አለበት።',
    'filled' => ':attribute ዋጋ ሊኖረው ይገባል።',
    'gt' => [
        'numeric' => ':attribute ከ :value መብለጥ አለበት።',
        'file' => ':attribute መጠኑ ከ :value ኪሎባይት መብለጥ አለበት።',
        'string' => ':attribute ርዝመቱ ከ :value ፊደላት መብለጥ አለበት።',
        'array' => ':attribute ከ :value በላይ ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'gte' => [
        'numeric' => ':attribute ከ :value ጋር እኩል ወይም ከዚያ መብለጥ አለበት።',
        'file' => ':attribute መጠኑ ከ :value ኪሎባይት ጋር እኩል ወይም ከዚያ መብለጥ አለበት።',
        'string' => ':attribute ርዝመቱ ከ :value ፊደላት ጋር እኩል ወይም ከዚያ መብለጥ አለበት።',
        'array' => ':attribute :value ወይም ከዚያ በላይ ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'image' => ':attribute ምስል መሆን አለበት።',
    'in' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'in_array' => ':attribute በ :other ውስጥ የለም።',
    'integer' => ':attribute ኢንቲጀር (ሙሉ ቁጥር) መሆን አለበት።',
    'ip' => ':attribute ትክክለኛ የ IP አድራሻ መሆን አለበት።',
    'ipv4' => ':attribute ትክክለኛ የ IPv4 አድራሻ መሆን አለበት።',
    'ipv6' => ':attribute ትክክለኛ የ IPv6 አድራሻ መሆን አለበት።',
    'json' => ':attribute ትክክለኛ የ JSON ቅርጸት መሆን አለበት።',
    'lowercase' => ':attribute በትንንሽ ፊደላት መሆን አለበት።',
    'lt' => [
        'numeric' => ':attribute ከ :value ማነስ አለበት።',
        'file' => ':attribute መጠኑ ከ :value ኪሎባይት ማነስ አለበት።',
        'string' => ':attribute ርዝመቱ ከ :value ፊደላት ማነስ አለበት።',
        'array' => ':attribute ከ :value በታች ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'lte' => [
        'numeric' => ':attribute ከ :value ጋር እኩል ወይም ከዚያ ማነስ አለበት።',
        'file' => ':attribute መጠኑ ከ :value ኪሎባይት ጋር እኩል ወይም ከዚያ ማነስ አለበት።',
        'string' => ':attribute ርዝመቱ ከ :value ፊደላት ጋር እኩል ወይም ከዚያ ማነስ አለበት።',
        'array' => ':attribute :value ወይም ከዚያ በታች ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'mac_address' => ':attribute ትክክለኛ የ MAC አድራሻ መሆን አለበት።',
    'max' => [
        'numeric' => ':attribute ከ :max መብለጥ የለበትም።',
        'file' => ':attribute መጠኑ ከ :max ኪሎባይት መብለጥ የለበትም።',
        'string' => ':attribute ርዝመቱ ከ :max ፊደላት መብለጥ የለበትም።',
        'array' => ':attribute ከ :max በላይ ዝርዝሮች ሊኖሩት አይገባም።',
    ],
    'max_digits' => ':attribute ከ :max አሃዞች መብለጥ የለበትም።',
    'mimes' => ':attribute ከሚከተሉት አይነቶች አንዱ ፋይል መሆን አለበት: :values።',
    'mimetypes' => ':attribute ከሚከተሉት አይነቶች አንዱ ፋይል መሆን አለበት: :values።',
    'min' => [
        'numeric' => ':attribute ቢያንስ :min መሆን አለበት።',
        'file' => ':attribute መጠኑ ቢያንስ :min ኪሎባይት መሆን አለበት።',
        'string' => ':attribute ርዝመቱ ቢያንስ :min ፊደላት መሆን አለበት።',
        'array' => ':attribute ቢያንስ :min ዝርዝሮች ሊኖሩት ይገባል።',
    ],
    'min_digits' => ':attribute ቢያንስ :min አሃዞች ሊኖሩት ይገባል።',
    'missing' => ':attribute መኖር የለበትም።',
    'missing_if' => ':other :value ሲሆን :attribute መኖር የለበትም።',
    'missing_unless' => ':other :value ካልሆነ በስተቀር :attribute መኖር የለበትም።',
    'missing_with' => ':values ሲኖር :attribute መኖር የለበትም።',
    'missing_with_all' => ':values በሙሉ ሲኖሩ :attribute መኖር የለበትም።',
    'multiple_of' => ':attribute የ :value ብዜት መሆን አለበት።',
    'not_in' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'not_regex' => 'የ :attribute ቅርጸት ትክክል አይደለም።',
    'numeric' => ':attribute ቁጥር መሆን አለበት።',
    'password' => [
        'letters' => ':attribute ቢያንስ አንድ ፊደል መያዝ አለበት።',
        'mixed' => ':attribute ቢያንስ አንድ አቢይ እና አንድ ንዑስ ፊደል መያዝ አለበት።',
        'numbers' => ':attribute ቢያንስ አንድ ቁጥር መያዝ አለበት።',
        'symbols' => ':attribute ቢያንስ አንድ ምልክት መያዝ አለበት።',
        'uncompromised' => 'የገባው :attribute በውሂብ ጥሰት ውስጥ ተገኝቷል። እባክዎ የተለየ :attribute ይምረጡ።',
    ],
    'present' => ':attribute መኖር አለበት።',
    'prohibited' => ':attribute የተከለከለ ነው።',
    'prohibited_if' => ':other :value ሲሆን :attribute የተከለከለ ነው።',
    'prohibited_unless' => ':other :value ካልሆነ በስተቀር :attribute የተከለከለ ነው።',
    'prohibits' => ':attribute :other እንዳይኖር ይከለክላል።',
    'regex' => 'የ :attribute ቅርጸት ትክክል አይደለም።',
    'required' => ':attribute ያስፈልጋል።',
    'required_array_keys' => ':attribute የሚከተሉትን እሴቶች መያዝ አለበት: :values።',
    'required_if' => ':other :value ሲሆን :attribute ያስፈልጋል።',
    'required_if_accepted' => ':other ተቀባይነት ሲኖረው :attribute ያስፈልጋል።',
    'required_unless' => ':other :value ካልሆነ በስተቀር :attribute ያስፈልጋል።',
    'required_with' => ':values ሲኖር :attribute ያስፈልጋል።',
    'required_with_all' => ':values በሙሉ ሲኖሩ :attribute ያስፈልጋል።',
    'required_without' => ':values ሳይኖር ሲቀር :attribute ያስፈልጋል።',
    'required_without_all' => 'ከ :values አንዳቸውም ሳይኖሩ ሲቀሩ :attribute ያስፈልጋል።',
    'same' => ':attribute እና :other መመሳሰል አለባቸው።',
    'size' => [
        'numeric' => ':attribute :size መሆን አለበት።',
        'file' => ':attribute :size ኪሎባይት መሆን አለበት።',
        'string' => ':attribute :size ፊደላት መሆን አለበት።',
        'array' => ':attribute :size ዝርዝሮችን የያዘ መሆን አለበት።',
    ],
    'starts_with' => ':attribute ከሚከተሉት በአንዱ መጀመር አለበት: :values።',
    'string' => ':attribute ጽሁፍ (string) መሆን አለበት።',
    'timezone' => ':attribute ትክክለኛ የሰዓት ዞን መሆን አለበት።',
    'unique' => 'ይህ :attribute ከዚህ በፊት ተወስዷል።',
    'uploaded' => ':attribute መጫን አልተቻለም።',
    'uppercase' => ':attribute በአቢይ (capital) ፊደላት መሆን አለበት።',
    'url' => ':attribute ትክክለኛ URL መሆን አለበት።',
    'ulid' => ':attribute ትክክለኛ ULID መሆን አለበት።',
    'uuid' => ':attribute ትክክለኛ UUID መሆን አለበት።',


    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "rule.attribute" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'ስም',
        'email' => 'ኢሜል',
        'password' => 'የይለፍ ቃል',
        'password_confirmation' => 'የይለፍ ቃል ማረጋገጫ',
        'phone' => 'ስልክ ቁጥር',
        'address' => 'አድራሻ',
        'department_id' => 'የዲፓርትመንት ID',
        'is_active' => 'ንቁ ነው',
        'created_at' => 'የተፈጠረበት ጊዜ',
        'updated_at' => 'የተሻሻለበት ጊዜ',
    ],
];
