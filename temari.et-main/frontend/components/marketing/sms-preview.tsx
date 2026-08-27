import { LogoMark } from "@/components/ui/logo"
import { cn } from "@/lib/utils"

/**
 * An SMS thread as a guardian's phone shows it — the product's actual channel.
 * Messages are illustrative samples of real Temari SMS events, in Amharic.
 */
export function SmsPreview({ className }: { className?: string }) {
  return (
    <div
      className={cn(
        "w-full max-w-85 rounded-[2.5rem] border bg-card p-2.5 shadow-xl",
        className
      )}
      aria-hidden
    >
      <div className="overflow-hidden rounded-[2rem] border bg-background">
        <div className="flex items-center gap-3 border-b px-5 py-4">
          <LogoMark size="md" />
          <div>
            <p className="text-sm font-semibold">Temari</p>
            <p className="text-xs text-muted-foreground">SMS</p>
          </div>
        </div>
        <div className="space-y-3 p-4 pb-6">
          <SmsBubble time="8:42">
            ብሩክ ሰለሞን ዛሬ ጠዋት ትምህርት ቤት አልተገኘም። ምክንያት ካለ በፖርታሉ ያሳውቁ ወይም ለክፍል ኃላፊው
            ይደውሉ።
          </SmsBubble>
          <SmsBubble time="12:15">
            የመስከረም ወር ክፍያ 1,850 ብር ተረጋግጧል። ደረሰኝ፦ temari.et/verify/R7K2
          </SmsBubble>
          <SmsBubble time="16:03">
            የሴሚስተር 1 ሪፖርት ካርድ ወጥቷል። አማካይ 87.6። ዝርዝሩን በፖርታሉ ይመልከቱ።
          </SmsBubble>
        </div>
      </div>
    </div>
  )
}

function SmsBubble({
  children,
  time,
}: {
  children: React.ReactNode
  time: string
}) {
  return (
    <div className="max-w-[85%]">
      <div className="rounded-2xl rounded-bl-md bg-muted px-4 py-3 text-[13px] leading-relaxed">
        {children}
      </div>
      <p className="mt-1 pl-1 font-mono text-[10px] text-muted-foreground tabular-nums">
        {time}
      </p>
    </div>
  )
}
