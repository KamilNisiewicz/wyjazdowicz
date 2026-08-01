import { query } from "@anthropic-ai/claude-agent-sdk";
import { z } from "zod";

// Kryteria wypracowane w Fazie B (10XCHAMPION-PLAN.md) — Step 1 skilla 10x-impl-review-ci:
// ogólne review, zawsze uruchamiane, niezależne od zgodności z planem implementacji.
const REVIEW_SCHEMA = z.object({
  implementationCorrectness: z.number().describe(
    "Poprawność implementacji (skala 1-10). 1: logika błędna lub po cichu psuje istniejące " +
    "zachowanie (np. cichy regres w liczeniu bilansu/dystansu/passy/pecha). 10: diff robi " +
    "dokładnie to, co deklaruje, poprawnie na ścieżce głównej i w rozsądnych przypadkach brzegowych."
  ),
  typeDiscipline: z.number().describe(
    "Dyscyplina typowania (skala 1-10, kompensacja braku PHPStan/Larastan w tym projekcie). " +
    "1: nowe/zmienione metody lub właściwości bez jawnych typów (parametry, zwracana wartość, " +
    "typy właściwości). 10: każda nowa/zmieniona metoda i właściwość ma jawny typ."
  ),
  testRiskCoverage: z.number().describe(
    "Pokrycie testami proporcjonalne do ryzyka (skala 1-10). 1: nowa logika biznesowa (dystans, " +
    "bilans W/D/L, passa, wskaźnik pechowego kibica) dodana bez żadnego testu. 10: każda nowa " +
    "gałąź logiki biznesowej ma test w tests/Unit lub tests/Feature adekwatny do ryzyka."
  ),
  authorizationScoping: z.number().describe(
    "Autoryzacja / scoping zasobów (skala 1-10). 1: nowa trasa przyjmująca ID zasobu " +
    "(GameMatch/Team) sięga po model bezpośrednio (np. GameMatch::find($id)) bez scopingu przez " +
    "zalogowanego użytkownika. 10: dostęp idzie przez $request->user()->relacja(), a nowa trasa " +
    "z ID jest dopisana do kontraktu OwnershipContractTest."
  ),
  buildAssetIntegrity: z.number().nullable().describe(
    "Integralność build assetów (skala 1-10), TYLKO gdy diff dotyka plików w resources/ lub " +
    "*.blade.php — w przeciwnym razie zwróć null, nie zgaduj. 1: diff wprowadza nową klasę " +
    "Tailwind/util w Blade, nic nie potwierdza, że trafi do skompilowanego CSS. 10: diff " +
    "pokazuje/dowodzi, że nowe klasy będą obecne w skompilowanym buildzie."
  ),
  verdict: z.enum(["pass", "fail"]).describe("Wiążący werdykt dla całej zmiany."),
  summary: z.string().describe("Podsumowanie w Markdown (2-3 zdania), gotowe jako komentarz do PR-a."),
});

const REVIEW_JSON_SCHEMA = z.toJSONSchema(REVIEW_SCHEMA, { target: "draft-07" });
type Review = z.infer<typeof REVIEW_SCHEMA>;

const SYSTEM_PROMPT = `Jesteś precyzyjnym, konstruktywnym recenzentem kodu oceniającym pull request
w projekcie Wyjazdowicz (Laravel 13 / PHP 8.3, aplikacja dla kibica śledzącego wyjazdy na mecze).

Oceń podany diff w pięciu kryteriach w skali 1-10 (1 = poważne braki, 10 = wzorowo):
poprawność implementacji, dyscyplina typowania, pokrycie testami proporcjonalne do ryzyka,
autoryzacja/scoping zasobów, integralność build assetów.

Pole "buildAssetIntegrity" oceniaj TYLKO jeśli diff dotyka plików w resources/ lub *.blade.php
(dostaniesz tę informację wprost w prompcie) — w przeciwnym razie zwróć null, nie zgaduj.

Następnie wydaj wiążący werdykt (pass/fail) dla całej zmiany i dołącz krótkie podsumowanie
(2-3 zdania) w Markdown, na podstawie którego autor PR-a będzie mógł działać.`;

function touchesBladeOrResources(diff: string): boolean {
  const changedFiles = [...diff.matchAll(/^diff --git a\/(\S+) b\/(\S+)/gm)]
    .flatMap((m) => [m[1], m[2]]);
  return changedFiles.some((f) => f.startsWith("resources/") || f.endsWith(".blade.php"));
}

async function readStdin(): Promise<string> {
  const chunks: Buffer[] = [];
  for await (const chunk of process.stdin) chunks.push(chunk as Buffer);
  return Buffer.concat(chunks).toString("utf8");
}

async function review(diff: string): Promise<Review> {
  const touchesAssets = touchesBladeOrResources(diff);
  const assetNote = touchesAssets
    ? "Ten diff DOTYKA plików resources/ lub *.blade.php — oceń buildAssetIntegrity normalnie."
    : "Ten diff NIE dotyka plików resources/ ani *.blade.php — zwróć buildAssetIntegrity jako null.";

  const result = query({
    prompt: `${assetNote}\n\nZrecenzuj ten diff:\n\n${diff}`,
    options: {
      systemPrompt: SYSTEM_PROMPT,
      model: "claude-sonnet-5",
      tools: [],
      maxTurns: 4,
      outputFormat: { type: "json_schema", schema: REVIEW_JSON_SCHEMA },
    },
  });

  for await (const message of result) {
    if (message.type !== "result") continue;
    if (message.subtype === "success") {
      const parsed = REVIEW_SCHEMA.safeParse(message.structured_output);
      if (!parsed.success) {
        throw new Error(`Niepoprawny structured output: ${parsed.error.message}`);
      }
      console.error(
        `[usage] turns=${message.num_turns} cost_usd=${message.total_cost_usd} ` +
        `input_tokens=${message.usage.input_tokens} output_tokens=${message.usage.output_tokens}`
      );
      return parsed.data;
    }
    throw new Error(`Review nie powiodło się (${message.subtype}): ${message.errors.join("; ")}`);
  }
  throw new Error("Agent nie zwrócił wyniku");
}

const diff = await readStdin();
if (!diff.trim()) {
  console.error("Brak diffa na stdin. Użycie: git diff | npx tsx review.ts");
  process.exit(1);
}
console.log(JSON.stringify(await review(diff), null, 2));
