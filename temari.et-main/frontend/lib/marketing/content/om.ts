import type { MarketingDict } from "./types"

export const om: MarketingDict = {
  locale: "om",
  announcement: {
    text: "Manneen barnootaa haaraan seemistara jalqabaa guutuu bilisaan hojjetu",
    cta: "Gatii ilaali",
  },
  nav: {
    features: "Tajaajilawwan",
    examPrep: "Qophii qormaataa",
    tutors: "Barsiisota dhuunfaa",
    pricing: "Gatii",
    about: "Waa'ee keenya",
    signIn: "Seeni",
    getStarted: "Jalqabi",
    openApp: "Appii bani",
    menu: "Baafata",
    language: "Afaan",
  },
  footer: {
    tagline:
      "Waltajjii mana barnootaa Itoophiyaaf ijaarame. Argama, kaffaltii, kaardii gabaasaa fi qophii qormaataa bakka tokkotti.",
    product: "Oomisha",
    audiences: "Eenyuuf",
    company: "Dhaabbata",
    columns: {
      product: [
        { label: "Tajaajilawwan", path: "/features" },
        { label: "Qophii qormaataa", path: "/exam-prep" },
        { label: "Gatii", path: "/pricing" },
        { label: "Gaaffilee", path: "/faq" },
      ],
      audiences: [
        { label: "Manneen barnootaaf", path: "/for/schools" },
        { label: "Barsiisotaaf", path: "/for/teachers" },
        { label: "Maatiif", path: "/for/parents" },
        { label: "Barattootaaf", path: "/for/students" },
        { label: "Barsiisota dhuunfaa", path: "/tutors" },
      ],
      company: [
        { label: "Waa'ee keenya", path: "/about" },
        { label: "Nu qunnamaa", path: "/contact" },
        { label: "Iccitii dhuunfaa", path: "/privacy" },
        { label: "Waliigaltee tajaajilaa", path: "/terms" },
      ],
    },
    copyright: "Temari.et",
    madeIn: "Finfinneetti hojjetame",
  },
  common: {
    learnMore: "Dabalata ilaali",
    getStarted: "Jalqabi",
    talkToUs: "Nu qunnamaa",
    seePricing: "Gatii ilaali",
    allFeatures: "Tajaajilawwan hunda",
    relatedFeatures: "Tajaajilawwan walitti dhufan",
    startPracticing: "Shaakala jalqabi",
  },
  ctaBand: {
    headline: "Mana barnootaa keessan gara Temari fidaa",
    sub: "Dameewwan keessan qindeessaa, barattoota galmeessaa, torban sanuma keessa argama galmeessuu jalqabaa — seemistarri keessan inni jalqabaa nurratti.",
    primary: "Jalqabi",
    secondary: "Nu qunnamaa",
  },
  home: {
    meta: {
      title: "Temari.et: Sooftweerii bulchiinsa mana barnootaa Itoophiyaaf",
      description:
        "Argama, kaffaltii mana barnootaa, kaardii gabaasaa, sagantaa yeroo, kaardii eenyummaa ismaartii fi SMS maatiif Afaan Oromoo, Amaariffaa fi Ingiliffaan. Dabalataan qophii qormaata kutaa 6, 8 fi 12 fi barsiisota dhuunfaa.",
    },
    audiences: {
      headline: "Maaltu as isin fide?",
      sub: "Temari waltajjii tokko, nama mana barnootaa naannoo jiru hundaaf mana qabu dha. Kan keessan filadhaa.",
      items: [
        {
          title: "Mana barnootaa nan bulcha",
          body: "Galmee, kaffaltii, argama, barnootaa fi hojjettoota damee hunda keessatti, iskiriinii tokko irratti kallattiin.",
          href: "/for/schools",
        },
        {
          title: "Nan barsiisa",
          body: "Argama daqiiqaa tokkotti, qabxii kaalkuleetara malee, karoora barnootaa fi hojii manaa bilbila keessan irraa.",
          href: "/for/teachers",
        },
        {
          title: "Maatii dha",
          body: "Argama, bu'aa fi kaffaltii daa'ima keessanii bilbila keessan irratti; kan ijoon SMSn.",
          href: "/for/parents",
        },
        {
          title: "Barataa dha",
          body: "Sagantaa, hojii manaa, kuwizii fi bu'aan keessan appii tokko keessatti, bilbila kamiyyuu irratti.",
          href: "/for/students",
        },
        {
          title: "Qormaata biyyaalessaaf qophaa'aa jira",
          body: "Qormaata shaakalaa kutaa 6, 8 fi 12 ibsa waliin — bilisaan jalqabaa, manni barnootaa hin barbaachisu.",
          href: "/exam-prep",
        },
        {
          title: "Barsiisaa dhuunfaa barbaada",
          body: "Barsiisaa dhuunfaa mirkanaa'e naannoo keessan argadhaa, waliigaltee godhaa, ji'a ji'aan nagaan kaffalaa.",
          href: "/tutors",
        },
      ],
    },
    hero: {
      badge: "ተማሪ · Barataaf",
      headline: "Mana barnootaa keessan guutuu",
      headline2: "bakka tokkoo bulchaa",
      sub: "Argama, kaffaltii, kaardii gabaasaa fi SMS maatiif. Manneen barnootaa Itoophiyaatiif, bilbila kamiyyuu irratti, afaan sadiin kan hojjetame.",
      primary: "Jalqabi",
      secondary: "Nu qunnamaa",
      note: "≈ daa'ima tokkoof guyyaatti saantima 55 · Manneen barnootaa haaraaf seemistarri jalqabaa bilisa",
    },
    banks: {
      title: "Kaffaltii karaa maatiin duraanuu fayyadaman galu mirkaneessaa",
    },
    schools: {
      title: "Manneen barnootaa Temari irratti hojjetan",
    },
    stats: [
      { value: "3", label: "Afaanota, bakka hundatti" },
      { value: "6·8·12", label: "Kutaalee qormaata biyyaalessaa haguugaman" },
      { value: "13", label: "Ji'oota, dhaha tokko" },
      { value: "24/7", label: "Yeroo kamiyyuu, eessattuu" },
    ],
    testimonials: {
      headline: "Manneen barnootaa maal jedhu",
      sub: "Waajjira irraa gara kutaa, gara maaddii maatiitti.",
      items: [
        {
          quote:
            "Temari dura seemistara cufuun rejistraara keenyaaf hojii wal bira qabiinsa waraqaa torban lamaa ture. Amma kaardiin gabaasaa guyyaa seemistarri cufametti qophaa'a.",
          name: "Raahel Tasfaayee",
          role: "Dura bu'aa, Finfinnee",
        },
        {
          quote:
            "Ganama ilmi koo barnoota hafe SMSn na qaqqaba. Kun qofti maatii keenyaaf gahaa ture.",
          name: "Mohaammad Yusuuf",
          role: "Abbaa ijoollee lamaa",
        },
        {
          quote:
            "Argamni daqiiqaa tokkoo gadi natti fudhata; qabxiin kuwizii kallattiin tarree koo seena. Dhugumaan caalaa barsiisa.",
          name: "Salaamaawit Baqqalaa",
          role: "Barsiistuu herregaa, kutaa 9",
        },
      ],
    },
    features: {
      headline: "Wanti manni barnootaa hojjetu hundi sirna tokko keessa",
      sub: "Galmee tokko barataa tokkoof, galmee irraa hanga tiraanskiriiptiitti. Lamata barreessuun hin jiru, galmeen waraqaa cinaa hin jiru.",
    },
    tour: {
      headline: "Hojii irratti ilaalaa",
      sub: "Fuulota Temari dhugaa — oomishuma tokko kutaa keessatti bilbilaan, waajjira keessatti kompiitaraan.",
      items: [
        {
          title: "Mana barnootaa guutuu ilaalcha tokkoon",
          body: "Galmee, argama, galii fi wanti murtee eeggatu hundi — kallattiin, damee hunda keessatti.",
        },
        {
          title: "Galmee barattootaa yeroo hunda haaraa",
          body: "Barataan hundi haala, kutaa fi eenyummaa waliin — afaan sadiin maqaadhaan barbaadamu, Excel irraa daqiiqaa muraasatti galu.",
        },
        {
          title: "Sagantaa walitti bu'iinsa hin qabne",
          body: "Argama barsiisotaa, kutaalee fi yeroo dachaa irratti hundaa'ee ofumaan maddisiifamee — hundaaf maxxanfama.",
        },
        {
          title: "Nagahee hunda, qarshii hunda",
          body: "Kaffaltii ji'aa ofumaan dhaha Itoophiyaatiin, kaffaltii mirkanaa'ee fi herrega yeroo hunda madaalu.",
        },
        {
          title: "Karoora barnootaa akkaataa ministeeraatiin",
          body: "Karoora waggaa fi kan torbanii — bakkuma barsiisonni hojjetanitti gamaggamamee hayyamama.",
        },
      ],
    },
    parents: {
      headline: "Maatiin smartphone malee odeeffannoo argatu",
      sub: "SMS karaa isa duraa ti. Hafuun, yaadachiisni kaffaltii fi bu'aan afaan filatameen bilbila maatii bira ga'u.",
      points: [
        {
          title: "Hafuun ganama sanuma beekama",
          body: "Daa'imni akka hafetti yoo galmaa'e, bilbilli guddisaa ergaa kan argatu laaqana dura malee dhuma seemistaraa miti.",
        },
        {
          title: "Yaadachiisa kaffaltii hanga ibsu",
          body: "Maatiin hangam, yoom, eessatti akka kaffalan sirriitti argu. Nagaheen koodii QR namni kamiyyuu mirkaneessuu danda'uun dhufa.",
        },
        {
          title: "Bu'aa hiriira malee",
          body: "Kaardiin gabaasaa fi cuunfaan argamaa portaalii maatii keessa jiru; lakkoofsonni ijoon SMSn ni ergamu.",
        },
      ],
    },
    ethiopia: {
      headline: "Itoophiyaaf kan ijaarame malee kan itti sirreeffame miti",
      sub: "Wantoonni sooftweerii biyya alaa cabsan asitti hundee dha.",
      items: [
        {
          title: "Dhaha fi sa'aatii Itoophiyaa",
          body: "Waggoonni barnootaa, seemistaroonni fi mindaan dhaha Itoophiyaatii fi sa'aatii lakkoofsa Itoophiyaatiin hojjetu. Guyyaa Giriigooriyaanii yookiin sa'aatii idilee filachuu barbaadduu? Qindaa'ina tokko qofa — sanadoonni seera qabeessi dhaha lamaanuu maxxansu.",
        },
        {
          title: "Afaan sadii",
          body: "Fuulli, SMSn fi sanadoonni Afaan Oromoo, Amaariffaa fi Ingiliffaan hojjetu. Fayyadamaan hundi kan ofii filata.",
        },
        {
          title: "Interneetii dadhabaa irrattis hojjeta",
          body: "Fuulonni salphaa dha; bilbila Android gatii madaalawaa irratti saffisaa dha. Argamnii fi galchi qabxii yeroo networkiin citu illee itti fufu.",
        },
        {
          title: "Maqaa akkaataa Itoophiyaa",
          body: "Maqaa, maqaa abbaa, maqaa akaakayyuu. Caasaan daataa akkaataa Itoophiyaanonni dhugaan itti waamaman hordofa.",
        },
      ],
    },
    examPrep: {
      headline: "Qophii qormaata biyyaalessaa kutaa 6, 8 fi 12",
      sub: "Qormaata shaakalaa yeroon daangeffame silabasii biyyaalessaa irraa qophaa'e, deebii hundaaf ibsa waliin. Manni barnootaa isaa Temari fayyadamus fayyadamuu baatus barataa hundaaf banaa dha.",
      points: [
        "Gosa barnootaan, boqonnaan yookiin qormaata guutuu yeroon shaakali",
        "Bu'aa hatattamaa ibsa furmaataa waliin",
        "Barsiisaa AI afaan keetiin deebisu",
      ],
      cta: "Shaakala jalqabi",
    },
    trust: {
      headline: "Galmee manni barnootaa ittiin dhaabbatu",
      sub: "Nagahee, kaardii gabaasaa fi waraqaa mindaa of mirkaneessan; seenaa calʼisee hin jijjiiramne.",
      items: [
        {
          title: "Sanadoota of mirkaneessan",
          body: "Nagaheewwan, tiraanskiriiptonni fi xalayoonni jijjiirraa dhugummaa isaanii mirkaneessuuf koodii QR namni kamiyyuu iskaanii gochuu danda'u qabu.",
        },
        {
          title: "Seenaa hin sochoone",
          body: "Seemistarri yoo cufamu bu'aa fi galmeen kaffaltii ni cimfamu. Kaardiin gabaasaa har'a maxxanfame kan bara dhufu maxxanfamu waliin tokko dha.",
        },
        {
          title: "Hayyama keessatti ijaarame",
          body: "Hayyamni baasii, mirkaneessi tarree qabxii fi gamaggamni ergaa akkaataa manni barnootaa dhugaan itti gaafatamummaa qoodu hordofu.",
        },
      ],
    },
    pricing: {
      headline: "Guyyaatti saantima 55",
      sub: "Waltajjiin guutuun maatii tokkoof kana kaffalchiisa — galmee, bu'aa, SMS fi qophii qormaataa daa'ima isaaniitiif.",
      price: "Saantima 55",
      unit: "daa'ima tokkoof, guyyaatti",
      note: "Maatiin yeroo galmee waggaatti al tokko kaffalu. Manni barnootaa ijoodhaaf homaa hin kaffalu — seemistarri jalqabaa guutuun mana barnootaa haaraa guutumaan guutuutti bilisa.",
      cta: "Gatii ilaali",
    },
  },
  featuresIndex: {
    meta: {
      title:
        "Tajaajilawwan: wanta Temari mana barnootaa keessaniif hojjetu hunda",
      description:
        "Galmee barattootaa, argama beeksisa SMS waliin, kaardii eenyummaa ismaartii, sassaabbii fi mirkaneessa kaffaltii, madaallii itti fufiinsaa, kaardii gabaasaa, qormaata, koorsii, sagantaa yeroo, humna namaa, mindaa fi kuusaa meeshaa.",
    },
    hero: {
      headline: "Waltajjii tokko, guyyaa barnootaa guutuu",
      sub: "Moojuliin hundi galmee barataa tokkicha dubbisa, barreessa; kanaafuu waajjirri, kutaan fi portaaliin maatii yeroo hunda walii galu.",
    },
  },
  features: {
    "student-management": {
      name: "Galmee barattootaa",
      tagline:
        "Galmee irraa hanga jijjiirraatti barataa tokkoof galmee qulqulluu tokko.",
      meta: {
        title: "Sirna odeeffannoo barattootaa manneen barnootaa Itoophiyaaf",
        description:
          "Barattoota qajeelfama tartiibaan galmeessaa, galmee guutuu Excel irraa galchaa, guddiftota, sanadootaa fi jijjiirraa bulchaa. Maqaan akkaataa Itoophiyaa keessatti ijaarame.",
      },
      hero: {
        headline: "Barataa hundaaf galmee tokko, lamata barreessuun hin jiru",
        sub: "Galmeen, guddiftonni, sanadoonni, yaadannoon fayyaa fi seenaan galmee waliin jiraatu. Galmeen barataa dameewwan gidduu, manneen barnootaa gidduu illee hordofa.",
      },
      capabilities: [
        {
          title: "Galmee qajeelfamaan geggeeffamu",
          body: "Qajeelfamni tartiiba tartiibaan barataa, guddiftota, sanadootaa fi nagahee jalqabaa adeemsa tokkoon qabata. Galmeen lama ta'e osoo hin uumamin qabama.",
        },
        {
          title: "Excel irraa waliigalaan galchuu",
          body: "Galmee duraan qabdan daqiiqaa muraasa keessatti dabarfadhaa. Faayilichi tarree tarreen sakatta'ama; rakkinni barataa barataan gabaafama.",
        },
        {
          title: "Guddiftota hayyama dhugaa qaban",
          body: "Hidhanni guddisaa tokkoon tokkoo namni sun maal arguu fi maal kaffaluu akka danda'u murteessa. Barataan tokko guddiftota gahee adda addaa qaban hedduu qabaachuu danda'a.",
        },
        {
          title: "Jijjiirraa ragaa waliin",
          body: "Jijjiirraan manneen barnootaa Temari gidduu faayilicha fudhatee deema: sanadoota, seenaa fi qulqullaa'ina, xalayaa jijjiirraa QRn mirkanaa'u waliin.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Waraqaa eenyummaa barataa maxxanfamu, barattoota bilbilaa hin qabneef seensa QR waliin",
          "Kuusaa bara duraa: manni barnootaa duraa galmee bara ofii qofa qabata, kan haaraa gonkumaa hin argu",
          "Meeshaalee ramaddii fi madaalliii kutaalee galmee guddaadhaaf",
          "Murtee guddinaa fi jijjiirraa dhuma waggaa adeemsa tokkoon",
        ],
      },
      related: ["attendance", "fees", "grading"],
    },
    attendance: {
      name: "Argama",
      tagline:
        "Galmeessa guyyaa fi yeroon, dubbistoota kaardii fi SMS guyyaa sanaa.",
      meta: {
        title:
          "Argama mana barnootaa beeksisa SMS fi dubbistoota kaardii waliin",
        description:
          "Argama bilbila kamiyyuu irraa galmeessaa, yookiin dubbistoonni kaardii balbala irratti tuqamanii ofumaan haa galmeessan. Guddiftonni SMS guyyaa sanaa argatu. Gabaasni hafiinsa dagaagaa dursee adda baasa.",
      },
      hero: {
        headline: "Eenyu mana barnootaa akka jiru beekaa, maatiittis himaa",
        sub: "Barsiisonni itti gaafatamtoonni kutaa daqiiqaa tokkoo gadi keessatti bilbilaan galmeessu, yookiin dubbistuun kaardii balbala irraa ofumaan galmeessa. Hafuun ganama sanuma SMSn guddiftota bira ga'a.",
      },
      capabilities: [
        {
          title: "Galmeessa daqiiqaa tokkoo",
          body: "Tarreen kutaa hunda akka argamanitti dursee qindaa'a. Barsiisaan kan adda ta'an qofa tuqa; bilbila kamiyyuu irraa, network malee illee.",
        },
        {
          title: "Kaardii eenyummaa tuqamee galmeessu",
          body: "Barattoonni eenyummaa isaanii balbala irratti tuqu; galmeen ofumaan barreeffama. Mallattoon barsiisaa harkaan godhe yeroo hunda kan maashinii caala.",
        },
        {
          title: "SMS guddisaa guyyaa sanaa",
          body: "Hafuun ergaa ifa ta'e tokko afaan isaatiin guddisaadhaaf erga; taatee tokkoof lama akka hin ergamne qulqullaa'a.",
        },
        {
          title: "Gabaasa akkaataa qabatu",
          body: "Hafiinsi dagaagaa fi argamni guutuun ofumaan mul'atu; kutaan, sadarkaan yookiin dameedhaan.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Argama hojjettootaa hayyamaa fi ayyaana waliin",
          "Maatiin sababa hafiinsaa portaalii maatii irraa dhiyeessu",
          "Argama yeroo yerootiin manneen barnootaa sadarkaa lammaffaatiif",
          "Network yoo citu ni hojjeta; yoo deebi'u wal simsiisa",
        ],
      },
      related: ["id-cards", "communication", "timetable"],
    },
    "id-cards": {
      name: "Kaardii eenyummaa ismaartii",
      tagline:
        "Kaardii eenyummaa tuqamee argama barattootaa fi hojjettootaa galmeessu.",
      meta: {
        title: "Kaardii eenyummaa barataa ismaartii argama ofumaan galmeessu",
        description:
          "Kaardii eenyummaa ismaartii maxxanfamu barattootaa fi hojjettootaaf. Tuqaan balbala irraa argama ofumaan galmeessa, guddiftonni SMS guyyaa sanaa argatu, QRn kaardichaa barattoota bilbilaa hin qabne appii keessa galcha.",
      },
      hero: {
        headline: "Kaardii tokko: eenyummaa, argama, seensa",
        sub: "Barattoonni fi hojjettoonni kaardii isaanii balbala irratti tuqu; argamni ofumaan barreeffama. Kaarduma sanatu koodii QR barataa bilbilaa hin qabne herrega isaatti galchu qaba.",
      },
      capabilities: [
        {
          title: "Argama balbalaa ofumaan",
          body: "Dubbistuun balbala irra jiru tuqaa hunda yeruma ta'etti galmeessa. Hiriirri waajjiraa hin jiru, waraqaan waamicha ganamaa hin jiru.",
        },
        {
          title: "Hojjettootaa fi barattoota, sirna tokko",
          body: "Kaardiin hojjettootaa dubbistuu sanuma irratti hojjeta; argamni hojjettootaa maashinii biraa malee gara humna namaatti gala.",
        },
        {
          title: "Guddiftonni ganama sanuma itti himamu",
          body: "Barataan hin tuqin beeksisa SMS guyyaa sanaa maatiidhaaf afaan isaaniitiin kakaasa.",
        },
        {
          title: "Mallattoon barsiisaa yeroo hunda caala",
          body: "Maashinichi gargaaraa dha malee murteessaa miti. Mallattoon barsiisaan itti gaafatamaan kutaa harkaan godhe dubbistuu yeroo hunda irra aana.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "QRn kaardii irra jiru barattoota bilbilaa hin qabne PIN qofaan portaalii isaaniitti galcha",
          "Kaardiin bade battalumatti cufama; seenaa osoo hin badin bakka bu'a",
          "Dubbistoonni balbalaa fi dameedhaan hojjetu; hamma mooraa keessaniif ta'utti",
          "Kaardichi akkuma waan hundaa gara galmee barataa tokkichaatti deebi'a",
        ],
      },
      related: ["attendance", "student-management", "communication"],
    },
    fees: {
      name: "Kaffaltii fi faayinaansii",
      tagline:
        "Nagaheewwan, mirkaneessa Telebirr fi baankii, nagahee fi herrega mana barnootaa.",
      meta: {
        title:
          "Sassaabbii kaffaltii fi faayinaansii mana barnootaa Itoophiyaaf",
        description:
          "Barataa hunda ofumaan kaffalchiisaa, kaffaltii Telebirr fi baankii mirkaneessaa, nagahee QR maxxansaa, iskoolaarshiippii bulchaa, herrega mana barnootaa hojjedhaa.",
      },
      hero: {
        headline: "Qarshiin hundi galmaa'eera",
        sub: "Maatiin Telebirr, baankii yookiin harkaan kaffalu. Temari nagahee baasa, mirkaneessa, gabaasa — waajjirri yeroo hunda madaala qabata.",
      },
      capabilities: [
        {
          title: "Nagahee ofumaan",
          body: "Caasaan kaffaltii dhaha Itoophiyaatiin barataa hundaaf nagahee uuma; hir'inni obboleeyyanii fi iskoolaarshiippii imaammataan hojjetama malee yaadannoodhaan miti.",
        },
        {
          title: "Mirkaneessa kaffaltii",
          body: "Maatiin lakkoofsa wabii Telebirr yookiin baankii portaalii irraa galchu. Faayinaansiin herrega mana barnootaa irratti mirkaneessa; nagaheen ni kennama.",
        },
        {
          title: "Nagahee ragaa qabu",
          body: "Nagaheen hundi odeeffannoo maatii biraa utuu hin saaxilin dhugummaa isaa mirkaneessuuf koodii QR namni kamiyyuu iskaanii gochuu danda'u qaba.",
        },
        {
          title: "Herrega mana barnootaa",
          body: "Baasii hayyama ija lamaa qabu, bajata fi baasii dhugaa, gabaasa galmee maallaqaa fi kaffaltii mindaa; hundi galii waliin sirna tokko keessa.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Hir'ina dhaabbataa iskoolaarshiippii, ijoollee hojjettootaa fi obboleeyyaniif, tartiiba hayyamaa waliin",
          "Yaadachiisa kaffaltii hanga sirrii ibsu SMSn",
          "Balbala kaffaltii galmee: galmee fi kaffaltiin walsimu",
          "Herrega sassaabbii dameedhaan; seenaan gonkumaa hin jijjiiramu",
        ],
      },
      related: ["communication", "student-management", "hr-payroll"],
    },
    grading: {
      name: "Madaallii fi kaardii gabaasaa",
      tagline:
        "Madaallii itti fufiinsaa, tarree qabxii, kaardii gabaasaa sadarkaa qabu, tiraanskiriiptii.",
      meta: {
        title:
          "Madaallii itti fufiinsaa fi kaardii gabaasaa manneen barnootaa Itoophiyaaf",
        description:
          "Madaallii itti fufiinsaa karoorfadhaa, qabxii saffisaan galchaa, bu'aa seemistaraa sadarkaa waliin cimsaa, kaardii gabaasaa fi tiraanskiriiptii Itoophiyaa mirkaneessa QR waliin maxxansaa.",
      },
      hero: {
        headline:
          "Tarree qabxii irraa hanga kaardii gabaasaatti kaalkuleetara malee",
        sub: "Barsiisonni qabxii al tokko galchu. Giddugaleessi, sadarkaan, qubeen fi kaardiin gabaasaa imaammata madaallii mana barnootaa ofumaan hordofu.",
      },
      capabilities: [
        {
          title: "Karoora madaallii itti fufiinsaa",
          body: "Gosti barnootaa hundi madaallii isaatii fi ulfaatina isaa seemistaraan karoorfata. Tarreen qabxii karoora irraa maddisiifama; kanaafuu homtuu hin irraanfatamu.",
        },
        {
          title: "Galchi qabxii saffisaa fi nagaa",
          body: "Gabatee kutaa guutuudhaaf qophaa'e. Tarreewwan cufamanii fi seemistaroonni xumuraman dogoggoraan hin gulaalaman.",
        },
        {
          title: "Bu'aa seemistaraa cimfame",
          body: "Seemistarri yoo cufamu giddugaleessii fi sadarkaan kutaa ni cimfamu. Kaardiin gabaasaa fi murteen guddinaa galmee cimfame dubbisu; seenaan hin socho'u.",
        },
        {
          title: "Tiraanskiriiptii manni barnootaa ittiin falmatu",
          body: "Tiraanskiriiptiin gabatee waggaa Itoophiyaa bu'aa cimfame irraa maxxanfama; mana barnootaa irraa akka dhufe mirkaneessu koodii QR waliin.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Safartuu qabxii fi imaammata qubee manni barnootaa ofii murteessu",
          "Xiinxala raabsa qabxii kutaa fi gosa barnootaatiin",
          "Yaada guddinaa bu'aa cimfame irraa shallagamu",
          "Maxxansa waliigalaa kaardii gabaasaa fi tiraanskiriiptii",
        ],
      },
      related: ["lms", "student-management", "communication"],
    },
    lms: {
      name: "Qormaata fi hojii manaa",
      tagline:
        "Kuusaa gaaffilee, qormaata toora interneetii nagaa, hojii manaa fi meeshaalee barnootaa.",
      meta: {
        title:
          "Qormaata toora interneetii, hojii manaa fi meeshaalee barnootaa",
        description:
          "Kuusaa gaaffilee gosa barnootaa fi boqonnaadhaan ijaaraa, qormaata yeroon daangeffame geggeessaa, hojii manaa ulaagaa ifa ta'een sassaabaa, meeshaalee qooddadhaa. Qabxiin gara kaardii gabaasaatti wal simsiifama.",
      },
      hero: {
        headline: "Hojii kutaa ofumaan gara kaardii gabaasaatti galu",
        sub: "Kuwiziin, qormaannii fi hojiin manaa toora interneetiitiin geggeeffamu, hanga danda'ametti ofumaan qabxeeffamu, bu'aan kallattiin gara madaallii itti fufiinsaatti gala. Lamata barreessuun hin jiru.",
      },
      capabilities: [
        {
          title: "Kuusaa gaaffilee gosa barnootaatiin",
          body: "Barsiisonni kuusaa irra deebiin fayyadan gosa barnootaa, sadarkaa fi boqonnaadhaan ijaaru; barreeffama badhaadhaa, suuraa, mallattoo herregaa fi dubbisa waliin.",
        },
        {
          title: "Qormaata dhaabbatu",
          body: "Barataan hundi waraqaa makame daangaa yeroo cimaa qabu argata. Amalli shakkisiisaan qorannoodhaaf mallatteeffama malee ofumaan hin adabamu.",
        },
        {
          title: "Hojii manaa ulaagaa ifaatiin madaalamu",
          body: "Hojiin manaa fi pirojektonni dhiyeessii toora interneetiitiin sassaabu; madaallii ulaagaa ulaagaadhaan fi marii barataa tokkoon tokkoon waliin.",
        },
        {
          title: "Galmee qabxii tokko, lamata barreessuun hin jiru",
          body: "Kuwizii yookiin hojiin manaa qabxeeffame bakka madaallii waliin hidhata; qabxiin isaa ulfaatina karooraatiin sirreeffamee tarree keessa gala.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Meeshaalee al tokko fe'amanii kutaalee sirriif ergaman",
          "Koorsii moojulii, barnootaa fi hordoffii adeemsaa qaban",
          "Qormaata kutaalee hedduuf waraqaa tokko irraa, istaatistiksii kutaan",
          "Waraqaa qormaataa A4 maxxanfamu gaaffilee sanuma irraa",
        ],
      },
      related: ["courses", "grading", "timetable"],
    },
    courses: {
      name: "Koorsii fi meeshaalee",
      tagline:
        "Koorsii toora interneetii caasaa qaban, barnootaa fi meeshaalee qoodaman.",
      meta: {
        title: "Koorsii toora interneetii fi meeshaalee barnootaa manneen barnootaaf",
        description:
          "Koorsii moojulii fi barnoota qaban ijaaraa, adeemsa barataa tokkoon tokkoon hordofaa, meeshaalee al tokko fe'aa kutaalee sirriif qoodaa. Interneetii suutaaf kan hojjetame.",
      },
      hero: {
        headline: "Barnoota kutaa darbee jiraatu",
        sub: "Barsiisonni moojulii, barnootaa fi meeshaalee irraa koorsii ijaaru. Barattoonni bilbila kamiyyuu irraa hordofu; adeemsi barsiisaa fi maatiidhaafis ni mul'ata.",
      },
      capabilities: [
        {
          title: "Koorsii caasaa dhugaa qaban",
          body: "Moojulii, barnoota tartiibaan taa'an, dubbisaa fi viidiyoo — al tokko ijaaramanii kutaalee fi waggoota keessatti irra deebiin fayyadamu.",
        },
        {
          title: "Meeshaalee al tokko qoodaman",
          body: "Waraqaan hojii yookiin dubbisni al tokko fe'amee sadarkaa fi kutaalee sirriif ergama. Kutaa kutaan irra deebi'anii fe'uun hin jiru.",
        },
        {
          title: "Adeemsa mul'atu",
          body: "Iddoon barataan hundi koorsii keessatti gahe ni hordofama; barsiisaan eenyu akka duukaa bu'aa jiru qormaata dura beeka.",
        },
        {
          title: "Galmee qabxii waliin hidhame",
          body: "Kuwizii fi hojiin manaa koorsii keessa jiran bakka madaallii waliin hidhatu; hojiin koorsii bakka malutti lakkaa'ama.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Barnoonni tartiibaan yookiin al tokkotti banamu — barsiisaatu murteessa",
          "Fuulota salphaa fi miidiyaa dhiibame 3G irratti fe'aman",
          "Maatiin qabiyyee hayyamame barataan argu sanuma argu",
          "Qophii qormaata kutaa 6, 8 fi 12 cinatti hojjeta",
        ],
      },
      related: ["lms", "grading", "timetable"],
    },
    timetable: {
      name: "Sagantaa yeroo fi karoora barnootaa",
      tagline:
        "Sagantaa walitti bu'iinsa hin qabne ofumaan qophaa'uu fi karoora barnootaa torbanii.",
      meta: {
        title: "Uumaa sagantaa yeroo mana barnootaa fi karoora barnootaa",
        description:
          "Sagantaa walitti bu'iinsa hin qabne ofumaan uumaa; argama barsiisotaa, kutaalee fi seera gosa barnootaa kabaja. Karoora barnootaa waggaa fi torbanii hordoffii saffisaa waliin.",
      },
      hero: {
        headline: "Torban ofuma isaa saganteessu",
        sub: "Yeroowwan, gosa barnootaa fi barsiisota keessan ibsaa. Temari sagantaa walitti bu'iinsa hin qabne qopheessa; harkaan sirreessitanii seemistaraan maxxansitu.",
      },
      capabilities: [
        {
          title: "Uumuu ofumaan",
          body: "Sirnichi argama barsiisotaa, daangaa guyyaa, yeroo dachaa fi barbaachisummaa kutaa akka laabraatoorii kabaja. Iddoowwan cufaman irra deebi'anii uumuu keessa darbatu.",
        },
        {
          title: "Maxxansa fooyya'iinsaan",
          body: "Wixinee hojjedhaa, sirreessaa, seemistaraan maxxansaa. Barsiisonni fi maatiin yeroo hunda kan maxxanfame qofa argu.",
        },
        {
          title: "Karoora barnootaa waggaa fi torbanii",
          body: "Barsiisonni boqonnaa waggaa karoorfatu, itti aansuun barnoota torban torbaniin isaan irratti karoorfatu. Daayrektaroonni bakkuma sanatti gamaggamanii hayyamu.",
        },
        {
          title: "Saffisa wal qixxeessu",
          body: "Barnoonni torban darbee yoo hin haguugamne, karoorri itti aanu sababa gaafata. Hordoffiin haguuggii kutaan hundi eessa akka jiru agarsiisa.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Sagantaa yeroo seemistaraan; sa'aatii guyyaa jijjiiruun sagantaa hin cabsu",
          "Maatiin karoora hayyamamee fi mata duree torbanii portaalii irratti argu",
          "Sagantaa barsiisotaa fi ilaalcha yeroo duwwaa",
          "Qajeelfama jalqabaa mana barnootaa saffisaan gara sagantaa maxxanfameetti geessu",
        ],
      },
      related: ["lms", "attendance", "grading"],
    },
    communication: {
      name: "Ergaa fi SMS",
      tagline:
        "Ujummoo beeksisaa tokko: fiidii keessoo appii, SMS fi imeelii, marii waliin.",
      meta: {
        title: "SMS fi ergaa mana barnootaa irraa gara maatiitti",
        description:
          "Beeksisa hafiinsaa, yaadachiisa kaffaltii fi bu'aa afaan maatiitiin SMSn. Ergaa keessoo appii hojjettootaa fi maatii gidduu, barsiisotaaf balbala hayyamaa waliin.",
      },
      hero: {
        headline: "Ergaa sirrii, karaa sirrii, al tokko",
        sub: "Wanti maatiin beekuu qabu hundi ujummoo tokkoon yaa'a: fiidiin keessoo appii yeroo hunda, SMS fi imeeliin yeroo barbaachisu, namni hundi afaan filateen.",
      },
      capabilities: [
        {
          title: "SMS bakka barbaachisutti",
          body: "Hafuun, yaadachiisni kaffaltii, nagaheen fi bu'aan bilbila guddisaa bira ga'u. Beeksisni murteessaan yeroo hunda darba; wacni ni qulqullaa'a.",
        },
        {
          title: "Afaan isaaniitiin",
          body: "Filannoon nama fudhataa hundaa afaan SMS, imeelii fi beeksisa hundaa murteessa. Afaan Oromoo, Amaariffaa yookiin Ingiliffaa.",
        },
        {
          title: "Marii kallattii fi garee",
          body: "Kutaalee hojjettootaa, chaanaalota kutaa fi ergaa kallattii; maatiin mana barnootaa waliin bakka tokkotti akka dubbataniif barataa hundaaf marii maatii waliin.",
        },
        {
          title: "Galmee quunnamtii balbala qabu",
          body: "Ergaan barsiisaa irraa gara maatiitti osoo hin ergamin dura hayyama itti gaafatamaa kutaa yookiin daayrektaraa gaafachuu danda'a; akkuma manneen barnootaa dhugaan hojjetan.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Filannoo beeksisaa akaakuudhaan fayyadamaa tokkoon tokkoon; taateewwan murteessoon cal'isuu darbu",
          "Beeksisa kaffaltii gaaffiidhaan kutaadhaaf yookiin mana barnootaa guutuuf",
          "Hidhannoo gadi fagoo: beeksisni hundi fuula ilaallatu bana",
          "To'annoo baasii SMS tarree sadarkaa waltajjiitiin",
        ],
      },
      related: ["attendance", "fees", "student-management"],
    },
    "hr-payroll": {
      name: "Humna namaa fi mindaa",
      tagline:
        "Hojjettoota, iddoowwan hojii, hayyama, argamaa fi mindaa Itoophiyaa.",
      meta: {
        title: "Humna namaa fi kaffaltii mindaa mana barnootaa Itoophiyaaf",
        description:
          "Faayilii hojjettootaa, iddoowwan hojii, hayyama akka Labsii 1156/2011, argama hojjettootaa fi mindaa gibira galii Itoophiyaa fi soorama sirriitti shallagu.",
      },
      hero: {
        headline: "Gama hojjettootaa, sirnaan hojjetame",
        sub: "Qacarrii irraa hanga waraqaa mindaatti: faayilii hojjettootaa, iddoowwan hedduu, hafteewwan hayyamaa waggaa Itoophiyaatiin, fi mindaa gibiraa fi soorama seeraan shallagu.",
      },
      capabilities: [
        {
          title: "Faayilii hojjettootaa dhugaa",
          body: "Nama tokkoof damee tokkotti faayilii tokko; sanadoota, iddoowwanii fi seenaa waliin. Barsiisaan daayrektaras ta'e nama tokko, hojii lama dha.",
        },
        {
          title: "Hayyama seeraan",
          body: "Gosoonni hayyamaa durtii Labsii 1156/2011 hordofu; guyyoota hojiitiin waggaa hayyamaa Itoophiyaatiin lakkaa'amu; adeemsa hayyamaa waliin.",
        },
        {
          title: "Mindaa cimfamu",
          body: "Gibirri galii akka Labsii 1395/2017 fi soorama 7/11 ofumaan shallagamu. Kaffaltiin hayyamame qoodinsa isaa bara baraan cimsa.",
        },
        {
          title: "Waraqaa mindaa fi ibsa",
          body: "Hojjettoonni waraqaa mindaa, haftee hayyamaa fi argama ofii tajaajila ofiitiin argu. Faayinaansiin gabaasa argata.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Iddoowwan gahee waliin hidhaman: barsiisaa qacaruun ofumaan seensa barsiisaa kenna",
          "Argama hojjettootaa ayyaanaa fi hayyama hayyamame waliin",
          "Durgoo, hir'inaa fi liqaan qoodinsa cimfame keessatti",
          "Waraqaa mindaa QRn mirkanaa'u",
        ],
      },
      related: ["fees", "attendance", "communication"],
    },
    inventory: {
      name: "Kuusaa meeshaa fi qabeenya",
      tagline:
        "Galmee kuusaa, gaaffii meeshaa, itti gaafatamummaa qabeenyaa fi ergisa kitaabaa.",
      meta: {
        title: "Bulchiinsa kuusaa meeshaa, qabeenyaa fi kitaabaa mana barnootaa",
        description:
          "Kaardii biinii dijitaalaa meeshaa kuusaa hundaaf, gaaffii meeshaa hojjettootaa hayyama waliin, ajaja bittaa, lakkaawwii, galmee qabeenyaa itti gaafatamummaa waliin fi ergisa kitaabaa barataadhaan.",
      },
      hero: {
        headline: "Meeshaan hundi lakkaa'ame, qabataan beekame",
        sub: "Cirrachaa irraa hanga laaptooppii fi kitaabaatti: galmee kuusaa yeroo hunda haaraa, hayyama caasaa keessaniin wal simu fi deebii ifaa gaaffii \"eenyutu qaba?\" jedhuuf.",
      },
      capabilities: [
        {
          title: "Kaardii biinii dijitaalaa",
          body: "Sochiin meeshaa hundi galmee mallatteeffame haftee wajjin dha — galmee kuusaa odiitarri keessan beeku, ofumaan qabamu.",
        },
        {
          title: "Gaaffii meeshaa hayyama waliin",
          body: "Hojjettoonni gaafatu, hayyamaan mirkaneessa — gaaffii ofii gonkumaa miti — itti gaafatamaan kuusaa kenna. Kennaa gartokkees ni danda'ama.",
        },
        {
          title: "Galmee qabeenyaa itti gaafatamummaa waliin",
          body: "Laaptooppiin, pirojektaroonni fi meeshaaleen laabraatoorii mallattoo fi hidhaa itti gaafatamummaa qabu. Qulqullaa'inni gaaffii tokko ta'a: namni kun hunda deebiseeraa?",
        },
        {
          title: "Ergisa kitaabaa kutaadhaan",
          body: "Kitaaba kutaa guutuudhaaf tarkaanfii tokkoon kennaa, deebi'uu barataadhaan hordofaa, koppiin kam akka hin deebine sirriitti ilaalaa.",
        },
      ],
      deepDive: {
        title: "Moojulii kana keessatti dabalataan",
        points: [
          "Ajaja bittaa filannoo manneen barnootaa sirnaan bitaniif",
          "Lakkaawwiin garaagarummaa haftee kallattii irratti galmeessa",
          "Meeshaan sadarkaa irra deebi'anii ajajuu yoo darbu beeksisa kuusaa xiqqaa",
          "Meeshaaleen seenaa qaban ni cufamu malee hin haqaman",
        ],
      },
      related: ["hr-payroll", "fees", "student-management"],
    },
  },
  audiences: {
    schools: {
      name: "Manneen barnootaa",
      meta: {
        title: "Temari manneen barnootaa fi daayrektarootaaf",
        description:
          "Mana barnootaa guutuuf waltajjii tokko: galmee, argama, kaffaltii, kaardii gabaasaa, sagantaa, humna namaa fi mindaa. Jalqabumaa kaasee damee hedduu; ijoon maatiidhaan kaffalama.",
      },
      hero: {
        headline: "Mana barnootaa guutuu, iskiriinii tokko irraa mul'atu",
        sub: "Daayrektaroonni fi dura bu'oonni galmee, argama, galii fi barnoota kallattiin argu; damee hunda keessatti, nama tokkollee osoo hin bilbilin.",
      },
      points: [
        {
          title: "Seemistarri keessan inni jalqabaa bilisa",
          body: "Utuu homaa hin kaffalin waltajjii guutuu mana barnootaa keessan guutuu waliin seemistara guutuu tokkoof geggeessaa. Kaardii gabaasaa dhugaa harkaa qabdanii murteessaa.",
        },
        {
          title: "Ijoon mana barnootaatiif bilisa",
          body: "Maatiin waltajjichaaf daa'ima tokkoof guyyaatti saantima 55 qofa kaffalu. Manni barnootaa kan kaffalu dabaliinsa filannoo filate qofaaf.",
        },
        {
          title: "Seensa isin waliin hojjenna",
          body: "Galmee barattootaa keessan Excel irraa isiniif galchina, kaffaltii fi kutaalee isin waliin qindeessina, hojjettoota keessan Afaan Oromoo, Amaariffaa yookiin Ingiliffaan leenjifna.",
        },
        {
          title: "Guyyaa jalqabaa irraa damee hedduu",
          body: "Dameen hundi hojii ofii geggeessa; hoggansi mana barnootaa garuu iddoo hojii tokko irraa hunda irratti hojjeta.",
        },
        {
          title: "Maallaqa to'annoo qabu",
          body: "Hayyama baasii ija lamaa, daangaa faayinaansii daayrektaraa fi seenaa kaffaltii cimfame. Addaan baafama odiitaroonni keessan eegan waltajjichi ni raawwachiisa.",
        },
        {
          title: "Qophii guyyootaan safaramu",
          body: "Galmee barattootaa Excel irraa galchaa, kaffaltii fi kutaalee ibsaa, torban sanuma argama galmeessaa.",
        },
      ],
      featuresTitle: "Wanta gareen keessan argatu",
      featureLinks: [
        "student-management",
        "fees",
        "attendance",
        "id-cards",
        "grading",
        "timetable",
        "hr-payroll",
        "inventory",
      ],
    },
    teachers: {
      name: "Barsiisota",
      meta: {
        title: "Temari barsiisotaaf",
        description:
          "Argama daqiiqaa tokkotti, galchi qabxii kaalkuleetara malee, karoora barnootaa, kuwizii fi hojii manaa toora interneetii, sagantaa kiisii keessan keessa. Bilbila kamiyyuu irratti.",
      },
      hero: {
        headline: "Waraqaa xiqqaa, barsiisuu guddaa",
        sub: "Argama, qabxii, karoora barnootaa fi hojii manaa bilbila kiisii keessan keessa jiru irraa. Hojiin barreeffamaa barsiisuu irra caalaan badeera.",
      },
      points: [
        {
          title: "Argama daqiiqaa tokkoo gadi",
          body: "Hundi akka argamanitti dursee qindaa'a. Kan adda ta'an tuqxanii xumurtu; yeroo networkiin walii hin galle illee.",
        },
        {
          title: "Qabxii herrega malee",
          body: "Qabxii kallattii galchaa; ulfaatinni, giddugaleessi, sadarkaan fi qubeen imaammata mana barnootaa ofumaan hordofu.",
        },
        {
          title: "Hojii manaa ofumaan qabxeeffamu",
          body: "Kuwiziin ofumaan qabxeeffamee tarree keessan keessa gala. Hojiin manaa dhiyeessii bakka tokkotti sassaaba.",
        },
        {
          title: "Guyyaan keessan ilaalcha tokkoon",
          body: "Yeroowwan har'aa, kutaaleen keessanii fi hojiin eeggataa yeroo appii bantan iskiriinii tokko irratti.",
        },
      ],
      featuresTitle: "Guyyaa keessan keessatti ijaarame",
      featureLinks: ["attendance", "grading", "lms", "timetable"],
    },
    parents: {
      name: "Maatii",
      meta: {
        title: "Temari maatii fi guddiftootaaf",
        description:
          "Argama, bu'aa fi kaffaltii daa'ima keessanii bilbila keessan irraa yookiin SMSn Afaan Oromoo, Amaariffaa yookiin Ingiliffaan hordofaa. Kallattiin mana barnootaatiif kaffalaa; nagahee mirkanaa'u argadhaa.",
      },
      hero: {
        headline: "Daa'imni keessan akkam akka jiru har'uma beekaa",
        sub: "Argama, bu'aa fi kaffaltii bilbila keessan irratti; kan ijoon SMSn. Herregni tokko ijoollee keessan hunda haguuga; manneen barnootaa adda addaa keessa yoo jiraatan illee.",
      },
      points: [
        {
          title: "Beeksisa hafiinsaa guyyaa sanaa",
          body: "Daa'imni keessan akka hafetti yoo galmaa'e, ganama sanuma SMSn afaan keessaniin dhageessu.",
        },
        {
          title: "Kaffaltii ajaa'iba malee",
          body: "Maal fi yoom akka kaffalamu sirriitti ilaalaa. Telebirr yookiin baankiidhaan kallattiin mana barnootaatiif kaffalaa, wabii galchaa, nagahee QRn mirkanaa'e argadhaa.",
        },
        {
          title: "Bu'aa akkuma ba'een",
          body: "Kaardiin gabaasaa, cuunfaan argamaa fi karoorri barnootaa hayyamame portaalii keessan keessa jiru. Balbala mana barnootaa dura hiriiruun hin jiru.",
        },
        {
          title: "Ijoollee keessan hunda, herrega tokko",
          body: "Daa'imni isin waliin hidhame hundi appii tokko keessatti mul'ata; guddisaan tokkoon tokkoon maal akka argu manni barnootaa to'ata.",
        },
      ],
      featuresTitle: "Wanta arguu dandeessan",
      featureLinks: ["attendance", "fees", "grading", "communication"],
    },
    students: {
      name: "Barattoota",
      meta: {
        title: "Temari barattootaaf",
        description:
          "Sagantaa, hojii manaa, bu'aa kuwizii fi kaardii gabaasaa keessan appii tokko keessatti ilaalaa. Qormaata biyyaalessaa kutaa 6, 8 fi 12tiif deebii hatattamaa waliin shaakalaa.",
      },
      hero: {
        headline:
          "Kutaaleen, hojiin manaa fi bu'aan keessan appii tokko keessa",
        sub: "Yeroowwan har'aa ilaalaa, hojii manaa dhiyeessaa, kuwizii fudhaa, qabxii keessan akkuma maxxanfameen ilaalaa. Bilbila kamiyyuu irratti hojjeta; herrega qabaachuuf smartphone hin barbaachisu.",
      },
      points: [
        {
          title: "Har'a, ilaalcha tokkoon",
          body: "Sagantaan, hojiin manaa ga'uu fi bu'aan haaraan iskiriinii jalqabaa akka appiiwwan beektanii qophaa'e tokko irratti.",
        },
        {
          title: "Hojii manaa bakka tokkotti",
          body: "Hojiin manaa hundi, guyyaan dhumaa fi haalli dhiyeessii keessanii. Bilbila irraa ergaa; deebii hordofaa.",
        },
        {
          title: "Kuwizii bu'aa hatattamaa waliin",
          body: "Kuwizii fi qormaata kutaa toora interneetiitiin fudhaa. Gaaffileen ofumaan qabxeeffaman battalumatti bu'aa agarsiisu; hundi galmee keessan keessa gala.",
        },
        {
          title: "Shaakala qormaataa keessatti ijaarame",
          body: "Qormaata shaakalaa kutaa 6, 8 fi 12tiif deebii hundaaf ibsa waliin; herrega kamiinuu bilisaan jalqabaa.",
        },
      ],
      featuresTitle: "Guyyaa barnootaa keessaniif hojjetame",
      featureLinks: ["lms", "timetable", "grading", "attendance"],
    },
  },
  examPrep: {
    meta: {
      title: "Qophii qormaata biyyaalessaa kutaa 6, 8 fi 12 Itoophiyaaf",
      description:
        "Qormaata biyyaalessaa Itoophiyaatiif qormaata shaakalaa yeroon daangeffame, qabxii hatattamaa, ibsa furmaataa fi barsiisaa AI waliin qophaa'aa. Bilisaan jalqabaa; bilbila kamiyyuu irratti hojjeta.",
    },
    hero: {
      badge: "Kutaa 6 · 8 · 12",
      headline: "Qormaata biyyaalessaa qophooftanii seenaa",
      sub: "Qormaata shaakalaa silabasii biyyaalessaa irraa qophaa'e, battalumatti qabxeeffamu, deebii hundaaf ibsa waliin. Bilbiluma amma qabdan irratti.",
      primary: "Shaakala jalqabi",
    },
    grades: [
      {
        grade: "Kutaa 6",
        title: "Xumura sadarkaa tokkoffaa",
        body: "Mallattoo biyyaalessaa isa jalqabaa dura gosa barnootaa hundaan bu'uura ijaaraa.",
      },
      {
        grade: "Kutaa 8",
        title: "Xumura sadarkaa giddu-galeessaa",
        body: "Shaakala gosa barnootaan; saffisaa fi ulfaatina waraqaa dhugaa waliin.",
      },
      {
        grade: "Kutaa 12",
        title: "Seensa yunivarsiitii (EUEE)",
        body: "Shaakala saayinsii uumamaa fi hawaasaatiif qophaa'e; qabxii isin barbaachisutti kan xiyyeeffate.",
      },
    ],
    how: {
      headline: "Akkaataa itti hojjetu",
      steps: [
        {
          title: "Herrega bilisaa uumaa",
          body: "Lakkoofsa bilbilaatiin galmaa'aa. Mana barnootaa Temari fayyadamu deemuu hin qabdan.",
        },
        {
          title: "Kutaa fi gosa barnootaa keessan filadhaa",
          body: "Baruuf boqonnaadhaan shaakalaa, yookiin haala qormaataa jalatti of qoruuf waraqaa guutuu yeroon fudhaa.",
        },
        {
          title: "Deebii hunda irraa baradhaa",
          body: "Gaaffiin hundi deebii sirrii fi ibsa furmaataa waliin deebi'a; deebiin dogoggoraa illee ni barsiisa.",
        },
      ],
    },
    ai: {
      headline: "Barsiisaa AI afaan keessan dubbatu",
      sub: "Fooyya'iinsi barsiisaa ibsu, irra deebi'ee ibsu fi bakka dadhabdanitti isin shaakalsiisu dabala.",
      points: [
        {
          title: "Waan kamiyyuu jechoota keessaniin gaafadhaa",
          body: "Furmaata irratti rakkattanii? Afaan Oromoo, Amaariffaa yookiin Ingiliffaan gaafadhaa; ibsa gaaffichaaf ta'u argadhaa.",
        },
        {
          title: "Karaa isiniin wal simu",
          body: "Seenaan shaakala keessanii wanta itti aanee argitan bocca; yeroon boqonnaalee isa barbaadaniif oola.",
        },
        {
          title: "Interneetii dadhabaa irrattis ni hojjeta",
          body: "Deebii gaggabaabaa fi xiyyeeffannaa qabu interneetii suuta'aa irrattuu fe'amu malee viidiyoo hin fe'amne miti.",
        },
      ],
    },
    pricingNote: {
      title: "Shaakalli bilisa; fooyya'iinsi AI ji'atti qarshii 199",
      body: "Qormaanni shaakalaa fi qabxiin hatattamaa herregaan bilisa. Barsiisaan AI fi karaan barnootaa wal simu fooyya'iinsa ji'aa ti; yeroo kamiyyuu dhaabuu dandeessu.",
    },
  },
  pricing: {
    meta: {
      title: "Gatii: barataa tokkoof waggaatti qarshii 200",
      description:
        "Waltajjiin ijoon barataa tokkoof waggaatti qarshii 200 dha; maatiidhaan kaffalama. Manneen barnootaa ijoodhaaf homaa hin kaffalan. Karoorri mana barnootaa fi fooyya'iinsi AI filannoo dha.",
    },
    hero: {
      headline: "Gatii salphaa, duraan dursee ibsame",
      sub: "Waltajjiin ijoon maatiidhaan kaffalama malee mana barnootaatiin miti. Dabaliinsi filannoo dha; gatiin isaa dhaabbataa fi murtee keessan dura kan mul'atu dha.",
    },
    freeSemester: {
      badge: "Osoo hin murteessin yaalaa",
      title: "Seemistarri keessan inni jalqabaa bilisa",
      body: "Mana barnootaa keessan guutuu fidaatii waan hunda geggeessaa — argama, kaffaltii, kaardii gabaasaa, SMS — utuu homaa hin kaffalin seemistara guutuu tokkoof. Kutaan barsiisotaa keessan erga amanee booda qofa murteessaa.",
      cta: "Bilisaan jalqabaa",
    },
    plans: [
      {
        name: "Waltajjii ijoo",
        price: "Saantima 55",
        unit: "barataa tokkoof, guyyaatti",
        perDay: "Waggaatti barataa tokkoof qarshii 200 ta'ee kaffalama",
        payer: "Yeroo galmee maatiidhaan kaffalama",
        description:
          "Sirna mana barnootaa guutuu. Manni barnootaa baasii sooftweerii hin qabu.",
        features: [
          "Galmee barattootaa, argamaa fi beeksisa SMS",
          "Kaffaltii, nagahee fi herrega faayinaansii",
          "Madaallii itti fufiinsaa, kaardii gabaasaa fi tiraanskiriiptii",
          "Sagantaa, karoora barnootaa, humna namaa fi mindaa",
          "Portaalii maatii fi barattootaa afaan sadiin",
        ],
        cta: "Jalqabi",
        href: "/signup",
        highlighted: true,
      },
      {
        name: "Karoora mana barnootaa",
        price: "Saantima 33",
        unit: "barataa tokkoof, guyyaatti",
        perDay: "Ji'atti barataa tokkoof qarshii 10 ta'ee kaffalama",
        payer: "Filannoo, mana barnootaatiin kaffalama",
        description:
          "Ofumaan hojjechuu ijoo irratti. Tajaajilawwan ijoon gonkumaa hin cufaman.",
        features: [
          "Mirkaneessa kaffaltii ofumaan (check.et)",
          "Nagahee galii elektirooniksii yeroo tajaajilli mootummaa jalqabu",
          "AI mana barnootaa hoggansaa fi barsiisotaaf",
        ],
        cta: "Nu qunnamaa",
        href: "/contact",
      },
      {
        name: "Qophii qormaataa AI",
        price: "Qarshii 6.5",
        unit: "guyyaatti",
        perDay: "Ji'atti qarshii 199 ta'ee kaffalama",
        payer: "Filannoo, maatiidhaan kaffalama",
        description:
          "Barattoota kutaa 6, 8 fi 12tiif. Shaakalli bilisaan itti fufa.",
        features: [
          "Barsiisaa AI Afaan Oromoo, Amaariffaa fi Ingiliffaan",
          "Karaa barnootaa seenaa shaakala keessanii irraa wal simu",
          "Qormaata shaakalaa daangaa hin qabne ibsa waliin",
        ],
        cta: "Shaakala jalqabi",
        href: "/exam-prep",
      },
    ],
    faqTitle: "Gaaffilee gatii",
    faq: [
      {
        q: "Osoo homaa hin kaffalin Temari yaaluu dandeenyaa?",
        a: "Eeyyee. Seemistarri jalqabaa guutuun mana barnootaa haaraa bilisa — waltajjii guutuu, mana barnootaa guutuu. Temari irratti seemistara cuftanii kaardii gabaasaa erga argitanii booda qofa murteessitu.",
      },
      {
        q: "Manni barnootaa waltajjii ijoodhaaf waa kaffalaa?",
        a: "Lakki. Waltajjiin ijoon barataa tokkoof guyyaatti gara saantima 55 ti — waggaatti qarshii 200 ta'ee yeroo galmee maatiidhaan kaffalama. Manni barnootaa kan kaffalu karoora mana barnootaa filannoo yookiin meeshaa dabalataa yoo filate qofa.",
      },
      {
        q: "Kaardii eenyummaa ismaartii fi dubbistoota balbalaa hoo?",
        a: "Meeshaalee filannoo ti. Nu qunnamaa; adeemsa seensaa keessatti kaardii fi dubbistoota hamma mooraa keessaniif ta'utti isiniif qopheessina.",
      },
      {
        q: "Maatiin qarshii 200 kaffaluu yoo hin dandeenye hoo?",
        a: "Nu qunnamaa. Haala kaffaltiin waltajjichaa gufuu itti ta'u irratti manneen barnootaa waliin ni hojjenna; barnoonni barataa kana irratti hundaa'uu hin qabu.",
      },
      {
        q: "Karoorri mana barnootaa dirqamaa?",
        a: "Miti. Mirkaneessa kaffaltii ofumaan godha, AI mana barnootaa dabala; garuu tajaajilli ijoon hundi isa malee hojjeta; gara isaatti hin dabarfaman.",
      },
    ],
  },
  about: {
    meta: {
      title: "Waa'ee Temari.et",
      description:
        "Temari.et Finfinneetti ijaarama: manneen barnootaa, maatii fi barattoota Itoophiyaatiif waltajjii tokko, afaan sadiin.",
    },
    hero: {
      headline: "Sooftweerii manneen barnootaa Itoophiyaa cimsee fudhatu",
      sub: "Temari jechuun barataa jechuu dha. Waltajjii manneen barnootaa Itoophiyaa malan ijaarra: dhahaan sirrii, gatiin lakkoofsa ifaan taa'e, bilbila namoonni dhugaan qaban irratti kan hojjetu.",
    },
    story: [
      "Sooftweeriin mana barnootaa Itoophiyaa keessatti gurguramu irra caalaan bakka biraatti qophaa'e. Dhahaan dogoggora, maqaan dogoggora, tilmaamni interneetii saffisaa fi kompiitara waajjiraa dogoggora; manni barnootaas hundaaf kaffala.",
      "Temari fiixee faallaa irraa jalqabe: mana barnootaa Itoophiyaa akkuma dhugaan hojjetutti. Seemistaroota lama Fulbaana jalqaban, madaallii itti fufiinsaa gara kaardii gabaasaa sadarkaa qabuutti galu, kaffaltii Telebirr fi baankiidhaan kaffalamu, maatii SMSn qaqqabaman.",
      "Temari Finfinnee taa'ee akka waltajjii tokkootti ijaarra; sababiin isaa manni barnootaa qaama tokko dha: waajjirri, kutaan fi maatiin galmee tokko irraa dubbisuu qabu.",
    ],
    values: [
      {
        title: "Lakkoofsa ifaa, ajaa'iba hin qabne",
        body: "Galiin keenya kaffaltii ji'aa fi waggaa gatiin isaa duraan dursee ibsame dha. Manneen barnootaa fi maatiin Temari maal akka kaffalchiisu yeroo hunda sirriitti beeku.",
      },
      {
        title: "Maatiin yaada boodaa miti",
        body: "Waltajjiin maatii smartphone qaban qofti fayyadamuu danda'an waltajjii mana barnootaa miti. SMS, afaan sadii fi herregni barattoota bilbilaa hin qabnee bu'uura dha.",
      },
      {
        title: "Ogummaa dura sirrummaa",
        body: "Bu'aan cimfame, daandiin maallaqaa galmaa'ee fi injinariingiin of eeggannoo qabu tajaajila haaraa kamiyyuu dura dhufu. Manneen barnootaa amantaadhaan hojjetu.",
      },
    ],
    factsTitle: "Gabaabaatti",
    facts: [
      { label: "Teessoo", value: "Finfinnee, Itoophiyaa" },
      {
        label: "Afaanota waltajjii",
        value: "Afaan Oromoo, Amaariffaa, Ingiliffaa",
      },
      { label: "Oomisha", value: "temari.et" },
    ],
  },
  contact: {
    meta: {
      title: "Temari.et qunnamaa",
      description:
        "Mana barnootaa keessan gara waltajjichaatti fiduu, michummaa yookiin deeggarsa irratti garee Temari waliin dubbadhaa. Teessoon keenya Finfinnee dha.",
    },
    hero: {
      headline: "Nu qunnamaa",
      sub: "Mana barnootaa tokko yookiin damee kudhan bulchitanis, waltajjichaa fi adeemsa seensaa isin agarsiifna.",
    },
    channels: [
      {
        title: "Bilbila",
        body: "Guyyaa hojii kamiyyuu afaan keessaniin nu bilbilaa.",
        value: "0988 155 377",
        href: "tel:+251988155377",
      },
      {
        title: "Bilbila",
        body: "Sararri lammaffaa, inni jalqabaa yoo qabame.",
        value: "0929 194 872",
        href: "tel:+251929194872",
      },
      {
        title: "Imeelii",
        body: "Agarsiisaaf, seensaaf fi waan biraa kamiifuu.",
        value: "info@temari.et",
        href: "mailto:info@temari.et",
      },
      {
        title: "Waajjira",
        body: "Garee Temari",
        value: "Finfinnee, Itoophiyaa",
        href: "",
      },
    ],
    schools: {
      title: "Mana barnootaa fiduuf yaaddanii?",
      body: "Baay'ina barattootaa fi akkaataa har'a kaffaltii itti bulchitan nutti himaa. Manni barnootaa baratamaan galmee isaa Excel irraa galchee torban jalqabaa keessa argama galmeessa.",
      cta: "Jalqabi",
    },
  },
  faq: {
    meta: {
      title: "Gaaffilee yeroo baay'ee gaafataman",
      description:
        "Deebii waa'ee Temari.et: gatii, SMS fi afaanota, fayyadama network malee, iccitii daataa, kaffaltii fi qophii qormaata kutaa 6, 8 fi 12.",
    },
    hero: {
      headline: "Gaaffilee yeroo baay'ee gaafataman",
      sub: "Deebii gaggabaabaa. Waan biraatiif nu qunnamaa.",
    },
    groups: [
      {
        title: "Waltajjicha",
        items: [
          {
            q: "Temari.et maali?",
            a: "Manneen barnootaa Itoophiyaatiif waltajjii tokko: galmee barattootaa, argama kaardii eenyummaa ismaartii waliin, kaffaltii, madaallii itti fufiinsaa, kaardii gabaasaa, qormaata, koorsii, sagantaa, humna namaa, mindaa fi kuusaa meeshaa — dabalataan portaalii maatii fi barattootaa, qophii qormaata biyyaalessaa fi gabaa barsiisota dhuunfaa.",
          },
          {
            q: "Dhaha fi sa'aatii Itoophiyaatiin hojjetaa?",
            a: "Eeyyee, uumamaan. Waggoonni barnootaa, seemistaroonni, waggoonni hayyamaa fi kaffaltiin irra deddeebi'u dhaha Itoophiyaatiin hojjetu; sa'aatiinis lakkoofsa Itoophiyaatiin mul'achuu danda'a. Manneen barnootaa guyyaa Giriigooriyaanii yookiin sa'aatii idilee filatan qindaa'ina keessatti jijjiiru — sanadoonni seera qabeessi dhaha lamaanuu maxxansu.",
          },
          {
            q: "Afaanota kam deeggara?",
            a: "Afaan Oromoo, Amaariffaa fi Ingiliffaa. Fayyadamaan hundi afaan ofii filata; kunis fuulaa fi SMS fi imeelii manni barnootaa ergu hundaaf hojjeta.",
          },
          {
            q: "Interneetii dadhabaa irratti hojjetaa?",
            a: "Interneetii dadhabaaf, bilbila Android gatii madaalawaa irratti kan qophaa'e dha. Hojiin ijoon akka argamaa fi galchii qabxii yeroo networkiin citu itti fufa; yoo deebi'u wal simsiisa.",
          },
          {
            q: "Manni barnootaa damee hedduu qabu fayyadamuu danda'aa?",
            a: "Eeyyee. Dameewwan bu'uura dha: hundi hojii ofii geggeessa; hoggansi mana barnootaa garuu iddoo hojii tokko irraa damee hunda irratti hojjeta.",
          },
        ],
      },
      {
        title: "Maallaqa",
        items: [
          {
            q: "Temaridhaaf eenyu kaffala?",
            a: "Maatiin waltajjii ijoodhaaf barataa tokkoof guyyaatti gara saantima 55 — waggaatti qarshii 200 ta'ee — kaffalu. Manni barnootaa ijoodhaaf homaa hin kaffalu; karoorri mana barnootaa filannoo barataa tokkoof ji'atti qarshii 10 dha. Seemistarri jalqabaa mana barnootaa haaraa bilisa.",
          },
          {
            q: "Karaaleen kaffaltii kam deeggaramu?",
            a: "Telebirr, CBE Birr, M-PESA fi daddabarsa gara herrega baankii mana barnootaa; maallaqa harkaa waajjiratti galmaa'u dabalatee. Nagaheen mirkaneessaaf koodii QR qaba.",
          },
        ],
      },
      {
        title: "Maatii fi barattoota",
        items: [
          {
            q: "Maatiin smartphone barbaaduu?",
            a: "Lakki. Hafiinsa, yaadachiisa kaffaltii fi bu'aadhaaf karaan ijoon SMS dha; afaan maatiitiin. Kanneen bal'ina barbaadaniif portaaliin jira.",
          },
          {
            q: "Barataan bilbila hin qabne herrega qabaachuu danda'aa?",
            a: "Eeyyee. Barattoonni eenyummaa barataa Temari fi PINiin seenuu danda'u. Qindeessuu fi PIN haaromsuun karaa bilbila guddisaa isa duraatiin nagaan darba.",
          },
          {
            q: "Qophiin qormaataa manneen barnootaa Temari qofaafi?",
            a: "Lakki. Namni kamiyyuu herrega bilisaa uumee qormaata biyyaalessaa kutaa 6, 8 fi 12tiif shaakaluu danda'a; manni barnootaa isaa Temari fayyadamus fayyadamuu baatus.",
          },
        ],
      },
      {
        title: "Daataa keessan",
        items: [
          {
            q: "Daataa barataa eenyu arguu danda'a?",
            a: "Mana barnootaa barataa fi guddiftota manni barnootaa hidhe, tokkoon tokkoon hayyama ofii waliin. Seensi caasaa dhugaa mana barnootaa dameedhaan hordofa.",
          },
          {
            q: "Barataan yoo jijjiiramu galmeen isaa maal ta'a?",
            a: "Faayilichi barataa waliin deema. Manni barnootaa duraa kuusaa cimfame bara ofii qofa qabata; wanta manni barnootaa haaraan dabalu gonkumaa hin argu.",
          },
          {
            q: "Sanadoonni akkamitti mirkanaa'u?",
            a: "Nagaheewwan, tiraanskiriiptonni, xalayoonni jijjiirraa fi waraqaaleen mindaa qabxii yookiin hanga utuu hin saaxilin dhugummaa mirkaneessu fuula mirkaneessaa uummataa banu koodii QR qabu.",
          },
        ],
      },
    ],
  },
}
