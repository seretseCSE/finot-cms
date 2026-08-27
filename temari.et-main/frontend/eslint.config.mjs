import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

import temari from "./eslint-rules.mjs";

/**
 * The date/time layer has exactly one entry point. `lib/dates.ts` owns the
 * Ethiopian-calendar and dawn-count-clock conversion; the two picker primitives
 * are the only other places allowed to touch a raw date library, because they
 * implement the widgets everything else uses.
 */
const DATE_LAYER_FILES = [
  "lib/dates.ts",
  "components/ui/date-picker.tsx",
  "components/ui/time-picker.tsx",
];

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
    // Cloudflare Workers build output (opennextjs-cloudflare). Bundled vendor
    // code — linting it produced ~28k phantom problems and was the reason the
    // lint script OOMed and therefore never ran.
    ".open-next/**",
    ".wrangler/**",
  ]),

  {
    plugins: { temari },
    rules: {
      "temari/require-delete-confirmation": "error",

      "no-restricted-imports": [
        "error",
        {
          paths: [
            {
              name: "date-fns",
              message:
                "Format dates through @/lib/dates (fmtDate / fmtTime / fmtDateTime …). It renders the school's active calendar (Ethiopian 13-month or Gregorian) and clock mode; date-fns always renders Gregorian, so it silently ignores the setting.",
            },
          ],
        },
      ],

      "no-restricted-syntax": [
        "error",
        {
          // Renders a Gregorian date regardless of the school's calendar_mode.
          selector:
            "CallExpression > MemberExpression.callee > Identifier.property[name=/^toLocale(Date|Time)String$/]",
          message:
            "toLocaleDateString/toLocaleTimeString render Gregorian and ignore the school's calendar_mode. Use fmtDate / fmtTime / fmtDateTime from @/lib/dates.",
        },
        {
          // Same bypass, different spelling — this one was live in 6 files.
          selector:
            "NewExpression > MemberExpression.callee > Identifier.property[name='DateTimeFormat']",
          message:
            "Intl.DateTimeFormat renders Gregorian and ignores the school's calendar_mode. Use @/lib/dates — fmtDate, fmtWeekday, fmtMonthName and weekdayName all exist for this.",
        },
        {
          selector:
            "CallExpression > MemberExpression.callee[object.name='window'] > Identifier.property[name='print']",
          message:
            "window.print prints the web page. Official paper renders server-side through the document pipeline — link to the generated PDF instead (see useDocumentDownload).",
        },
        {
          selector:
            "JSXOpeningElement[name.name='input'] > JSXAttribute[name.name='type'][value.value=/^(date|time|datetime-local)$/]",
          message:
            "Native date/time inputs show a Gregorian 12-month picker and a standard clock. Use the shared DatePicker / TimePicker, which render the Ethiopian 13-month grid and dawn-count wheels while still speaking ISO strings.",
        },
        {
          // Ethiopian names are patronymic: first + father + grandfather.
          selector: "Identifier[name='last_name']",
          message:
            "Ethiopian names are patronymic — there is no family surname. Use first_name / father_name / grandfather_name / mother_name.",
        },
        {
          selector: "Literal[value=/school-x\\.et/i]",
          message:
            "school-x.et is the deprecated platform and must never be referenced in code. The domain is temari.et.",
        },
      ],
    },
  },

  {
    // The date layer itself, and the picker widgets it backs.
    files: DATE_LAYER_FILES,
    rules: {
      "no-restricted-imports": "off",
      "no-restricted-syntax": "off",
    },
  },

  {
    // The rule definitions quote the very patterns they ban.
    files: ["eslint.config.mjs", "eslint-rules.mjs"],
    rules: {
      "no-restricted-syntax": "off",
      "import/no-anonymous-default-export": "off",
    },
  },
]);

export default eslintConfig;
