import type { ReportCardSkill } from "@/lib/types"

/**
 * The suggested behavioral-skill catalog for report cards — the common
 * checklist rows on Ethiopian progress reports, offered as one-tap additions
 * in the report-card settings. Mirrors the backend catalog
 * (App\Support\ReportCardSettings::suggestedSkills) — labels are school
 * content the school may edit after adding, so drift is cosmetic only.
 */
export const SUGGESTED_REPORT_CARD_SKILLS: ReportCardSkill[] = [
  { key: "academic_standard", group: "habits", label: { en: "Academic standard", am: "የትምህርት ደረጃ", om: "Sadarkaa barnootaa" } },
  { key: "handwriting", group: "habits", label: { en: "Handwriting", am: "የእጅ ጽሑፍ", om: "Barreeffama harkaa" } },
  { key: "does_homework", group: "habits", label: { en: "Does homework / classwork", am: "የቤት ሥራ / የክፍል ሥራ ይሠራል", om: "Hojii manaa / daree ni hojjeta" } },
  { key: "concentrates", group: "habits", label: { en: "Concentrates in class", am: "በክፍል ውስጥ ትኩረት ያደርጋል", om: "Daree keessatti xiyyeeffata" } },
  { key: "works_independently", group: "habits", label: { en: "Works independently", am: "በራሱ ይሠራል", om: "Ofiin hojjeta" } },
  { key: "completes_on_time", group: "habits", label: { en: "Completes work on time", am: "ሥራን በጊዜ ይጨርሳል", om: "Hojii yeroon xumura" } },
  { key: "follows_instructions", group: "habits", label: { en: "Follows directions / instructions", am: "መመሪያዎችን ይከተላል", om: "Qajeelfama hordofa" } },
  { key: "keeps_materials", group: "habits", label: { en: "Keeps materials clean and neat", am: "ቁሳቁሶችን በንጽህና ይይዛል", om: "Meeshaalee qulqullinaan qaba" } },
  { key: "punctuality", group: "habits", label: { en: "Comes to school on time", am: "በሰዓቱ ትምህርት ቤት ይገኛል", om: "Yeroon mana barumsaa dhufa" } },
  { key: "personal_hygiene", group: "character", label: { en: "Keeps personal hygiene", am: "የግል ንጽህናን ይጠብቃል", om: "Qulqullina dhuunfaa eega" } },
  { key: "wears_uniform", group: "character", label: { en: "Wears uniform regularly", am: "የደንብ ልብስ በመደበኛነት ይለብሳል", om: "Uffata seeraa yeroo hunda uffata" } },
  { key: "obeys_rules", group: "character", label: { en: "Obeys school rules", am: "የትምህርት ቤት ደንቦችን ያከብራል", om: "Seera mana barumsaa kabaja" } },
  { key: "polite", group: "character", label: { en: "Is polite", am: "ትሑት ነው", om: "Naamusa qaba" } },
  { key: "respects_others", group: "character", label: { en: "Respects teachers and elders", am: "መምህራንን እና ታላላቆችን ያከብራል", om: "Barsiisotaa fi maanguddoota kabaja" } },
  { key: "self_control", group: "character", label: { en: "Displays self-control", am: "ራስን መግዛት ያሳያል", om: "Of-to’annaa agarsiisa" } },
  { key: "self_confidence", group: "character", label: { en: "Displays self-confidence", am: "በራስ መተማመን ያሳያል", om: "Of-amanamummaa agarsiisa" } },
  { key: "shares", group: "character", label: { en: "Shares and cooperates", am: "ያካፍላል እና ይተባበራል", om: "Qooddatee walii gala" } },
  { key: "participates", group: "character", label: { en: "Participates in class activities", am: "በክፍል እንቅስቃሴዎች ይሳተፋል", om: "Sochii daree keessatti hirmaata" } },
]
