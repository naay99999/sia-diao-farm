---
name: เสี่ยเดียวฟาร์ม
description: ระบบจัดการสวนรายต้น ใช้บนเดสก์ท็อปเป็นหลัก เปิดดูบนมือถือได้ อ่านง่ายกลางแดด สร้างบน shadcn/ui
colors:
  durian-green: "oklch(0.46 0.11 150)"
  on-durian-green: "oklch(0.985 0 0)"
  brand-soft: "oklch(0.97 0.02 150)"
  brand-track: "oklch(0.94 0.03 150)"
  brand-ink: "oklch(0.33 0.08 150)"
  focus-ring: "oklch(0.62 0.11 150)"
  canvas: "oklch(1 0 0)"
  ink: "oklch(0.145 0 0)"
  quiet-surface: "oklch(0.97 0 0)"
  quiet-ink: "oklch(0.48 0 0)"
  border: "oklch(0.922 0 0)"
  field-border: "oklch(0.87 0 0)"
  destructive: "oklch(0.577 0.245 27.325)"
  sidebar-surface: "oklch(0.985 0 0)"
  chart-2: "oklch(0.6 0.118 184.704)"
  chart-3: "oklch(0.398 0.07 227.392)"
  chart-4: "oklch(0.828 0.189 84.429)"
  chart-5: "oklch(0.769 0.188 70.08)"
typography:
  key-number:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 600
    lineHeight: 1.3
  page-title:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.4
  section-title:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem / md 1.125rem"
    fontWeight: 600
    lineHeight: 1.5
  body:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem / md 1rem"
    fontWeight: 400
    lineHeight: 1.7
  control:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem / md 1rem"
    fontWeight: 500
    lineHeight: 1.5
  label:
    fontFamily: "IBM Plex Sans Thai Looped, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem / md 0.875rem"
    fontWeight: 500
    lineHeight: 1.6
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "14px"
  full: "9999px"
spacing:
  1: "4px"
  2: "8px"
  3: "12px"
  4: "16px"
  6: "24px"
  12: "48px"
components:
  button-primary:
    backgroundColor: "{colors.durian-green}"
    textColor: "{colors.on-durian-green}"
    rounded: "{rounded.md}"
    height: "48px / md 36px"
    padding: "0 16px"
  button-outline:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "48px / md 36px"
    padding: "0 16px"
  input:
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "48px / md 36px"
    padding: "4px 12px"
  surface:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "16px / md 20px"
  key-number-box:
    backgroundColor: "{colors.brand-soft}"
    textColor: "{colors.brand-ink}"
    rounded: "{rounded.lg}"
  share-bar:
    backgroundColor: "{colors.brand-track}"
    fill: "{colors.durian-green}"
    rounded: "{rounded.full}"
    height: "6px"
  bottom-nav-item:
    height: "64px"
    activeBackground: "{colors.brand-soft}"
    activeIcon: "{colors.durian-green}"
---

# Design System: เสี่ยเดียวฟาร์ม

ถ้าโค้ดกับไฟล์นี้ขัดกัน ให้แก้โค้ดให้ตรงไฟล์นี้ หรือแก้ไฟล์นี้ก่อนแล้วค่อยแก้โค้ด
ค่าใน frontmatter เป็นสรุปของ token ส่วนค่าที่ใช้จริงอยู่ใน `resources/css/app.css` (§Colors)

## Overview

**Creative North Star: "สมุดจดประจำสวน"**

แอปนี้คือสมุดจดประจำสวนที่เปิดบนโต๊ะทำงาน ใช้จัดการ แก้ และสรุปข้อมูลของทุกสวนได้ครบ
ในสวน คนในครอบครัวจดผ่าน Claude (MCP) และเปิดแอปบนมือถือเพื่อดูข้อมูลหรือสแกน QR ที่ต้น
จอบนมือถือจึงยังต้องอ่านออกกลางแดดในครั้งแรกที่มอง และกดถูกด้วยนิ้วโป้งข้างเดียว
ความน่าเชื่อถือมาจากตัวเลขที่ชัดและเรียงตรงกัน ไม่ได้มาจากการตกแต่ง

ผู้ใช้คือเจ้าของสวนทุเรียนและครอบครัว อายุราว 40–50 ปี สายตาเริ่มยาวและอ่านตัวเล็กไม่ถนัด
ใช้บน **เดสก์ท็อปเป็นหลัก** ทุกงานในแอปต้องทำได้ครบบนเดสก์ท็อป ส่วนมือถือใช้ดูข้อมูลและเปิดหน้าต้นจาก QR
ทุกหน้าต้อง responsive (ดู `docs/superpowers/specs/2026-10-24-product-direction-design.md`)
ดังนั้นทุกการตัดสินใจเลือก **อ่านง่ายและกดง่าย** ก่อนความแน่นของข้อมูล

พื้นฐานคือ **shadcn/ui** (style new-york, base color neutral, โหมดสว่างเท่านั้น) แล้วเพิ่มเพียงสามอย่าง:
**ตัวอักษรไทยแบบมีหัวที่ใหญ่ขึ้นหนึ่งขั้น**, **สีเขียวใบทุเรียนหนึ่งสี** เป็นสีของแบรนด์และข้อมูล,
และ **สีเทารองที่เข้มขึ้น** ให้อ่านได้กลางแดด

**Key Characteristics:**
- ตัวอักษรบนมือถือไม่เล็กกว่า 16px และเนื้อความ 18px ทุกอย่างที่กดได้สูงอย่างน้อย 48px
- สีเขียวสีเดียว ใช้เต็มเฉพาะจุดเล็ก ใช้อ่อนเป็นพื้นที่ ที่เหลือเป็นขาว เทา และดำ
- ตัวเลขสำคัญของจอใหญ่และอยู่ในกล่องเขียวอ่อน ตัวเลขในแถวชิดขวาและกว้างเท่ากัน
- ผิวแบน เส้นขอบบาง ไม่มีไล่สี ไม่มีเงาบนพื้นที่ที่วางนิ่ง
- คอมโพเนนต์ shadcn ตามที่ติดตั้งมา ปรับเฉพาะขนาดตัวอักษรและพื้นที่แตะ

## Colors

จานสีเกือบทั้งหมดเป็นค่า neutral ของ shadcn สีเดียวที่มีชีวิตคือเขียวใบทุเรียน
ซึ่งไล่เป็นขั้นบันไดตั้งแต่สีเต็มไปจนถึงสีอ่อนเกือบขาว

### Primary
- **เขียวใบทุเรียน** (`durian-green` → `--primary`, `--chart-1`, `--sidebar-primary`): ปุ่มหลัก แท่งข้อมูล ไอคอนเมนูที่เลือกอยู่ เส้นใต้แท็บที่เลือก ช่องที่ติ๊กแล้ว
- **ตัวอักษรบนสีเขียว** (`on-durian-green` → `--primary-foreground`): ตัวอักษรบนปุ่มหลัก
- **วง focus** (`focus-ring` → `--ring`, `--sidebar-ring`): ring ของคีย์บอร์ดโฟกัส

### Secondary (บันไดสีเขียว)
- **เขียวอ่อนสุด** (`brand-soft` → `--brand-soft`, `--sidebar-accent`): กล่องตัวเลขสำคัญของจอ แถวเมนูที่เลือกอยู่ ตัวเลือกที่เลือกแล้ว
- **รางเขียว** (`brand-track` → `--brand-track`): รางของแท่งข้อมูลส่วนที่ยังไม่เต็ม
- **หมึกเขียว** (`brand-ink` → `--brand-ink`, `--sidebar-accent-foreground`): ตัวหนังสือบน `brand-soft`

| token | ใช้กับ | ห้ามใช้กับ |
| --- | --- | --- |
| `primary` | ปุ่มหลักหนึ่งปุ่มต่อจอ, แท่งข้อมูล (ผลผลิตต่อต้น, สัดส่วนค่าใช้จ่าย), ไอคอนเมนูที่เลือก, เส้นใต้แท็บที่เลือก, ช่องที่ติ๊กแล้ว | พื้นที่ใหญ่, ตัวหนังสือเนื้อความ |
| `brand-soft` | กล่องตัวเลขสำคัญ (ผลผลิตรอบนี้, กำไรรอบนี้), แถวเมนูที่เลือก, ตัวเลือกที่เลือกแล้ว | ปุ่ม |
| `brand-track` | รางของแท่งข้อมูล | ตัวหนังสือ |
| `brand-ink` | ตัวหนังสือบน `brand-soft` | |

### Neutral
- **พื้นขาว** (`canvas` → `--background`, `--card`, `--popover`): พื้นหลักและพื้นของส่วนต่าง ๆ
- **หมึก** (`ink` → `--foreground`): ข้อความที่ต้องอ่าน ชื่อต้น ตัวเลข รายการ
- **ผิวเทา** (`quiet-surface` → `--muted`, `--secondary`, `--accent`): พื้นรอง hover และกล่องค่าใช้จ่ายรวม
- **หมึกเทา** (`quiet-ink` → `--muted-foreground`): ข้อมูลรอง เช่น หน่วย วันที่ ป้ายชื่อ เข้มกว่าค่าเริ่มต้นของ shadcn (0.556) เพื่ออ่านกลางแดด
- **เส้นบาง** (`border`) และ **เส้นช่องกรอก** (`field-border` → `--input`): ขอบช่องกรอกเข้มกว่าเส้นแบ่งทั่วไปเพื่อให้เห็นว่ากรอกได้
- **พื้น sidebar** (`sidebar-surface`): ชั้นนำทางบนเดสก์ท็อป
- **แดง** (`destructive`): การลบ และตัวเลขที่ติดลบ เช่น ขาดทุน เท่านั้น
- **สีกราฟ** (`chart-2`…`chart-5`): ค่าเริ่มต้นของ shadcn สำรองไว้เมื่อต้องแยกหลายชุดข้อมูลในกราฟเดียว ยังไม่มีจอที่ใช้

**The One Green Rule.** แอปมีสีเดียวคือเขียวใบทุเรียน สีเต็มใช้กับจุดเล็ก สีอ่อนใช้กับพื้นที่ ไม่มีสีที่สอง ไม่มีไล่สี

**The Green Is Not Good Rule.** สีเขียวคือสีของแบรนด์และข้อมูล ไม่ได้แปลว่า "ดี" ค่าใช้จ่ายรวมใช้กล่อง `bg-muted` ไม่ใช้ `brand-soft` เพราะไม่ใช่ตัวเลขของผลผลิต

**The Sunlight Rule.** ห้ามใช้สีเทาที่อ่อนกว่า `--muted-foreground` กับข้อความ และข้อความที่ต้องอ่านใช้ `text-foreground` เสมอ

### Token ใน CSS

ค่าเริ่มต้น neutral ของ shadcn/ui สำหรับ Tailwind v4 ใส่ใน `:root` ของ `resources/css/app.css`
**ยกเว้น** token ที่มีเครื่องหมาย `/* ฟาร์ม */` ชื่อ token ใน `@theme` (`--color-*`) คงไว้ตามเดิม
และเพิ่ม `--color-brand-soft`, `--color-brand-track`, `--color-brand-ink`

```css
:root {
    --radius: 0.625rem;
    --background: oklch(1 0 0);
    --foreground: oklch(0.145 0 0);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.145 0 0);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.145 0 0);
    --primary: oklch(0.46 0.11 150); /* ฟาร์ม: เขียวใบทุเรียน */
    --primary-foreground: oklch(0.985 0 0);
    --secondary: oklch(0.97 0 0);
    --secondary-foreground: oklch(0.205 0 0);
    --muted: oklch(0.97 0 0);
    --muted-foreground: oklch(0.48 0 0); /* ฟาร์ม: เข้มขึ้นจาก 0.556 */
    --accent: oklch(0.97 0 0);
    --accent-foreground: oklch(0.205 0 0);
    --destructive: oklch(0.577 0.245 27.325);
    --border: oklch(0.922 0 0);
    --input: oklch(0.87 0 0); /* ฟาร์ม: ขอบช่องกรอกเห็นชัดขึ้น */
    --ring: oklch(0.62 0.11 150); /* ฟาร์ม */
    --chart-1: oklch(0.46 0.11 150); /* ฟาร์ม: = primary */
    --chart-2: oklch(0.6 0.118 184.704);
    --chart-3: oklch(0.398 0.07 227.392);
    --chart-4: oklch(0.828 0.189 84.429);
    --chart-5: oklch(0.769 0.188 70.08);
    --brand-soft: oklch(0.97 0.02 150); /* ฟาร์ม */
    --brand-track: oklch(0.94 0.03 150); /* ฟาร์ม */
    --brand-ink: oklch(0.33 0.08 150); /* ฟาร์ม */
    --sidebar: oklch(0.985 0 0);
    --sidebar-foreground: oklch(0.145 0 0);
    --sidebar-primary: oklch(0.46 0.11 150); /* ฟาร์ม */
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-accent: oklch(0.97 0.02 150); /* ฟาร์ม: = brand-soft */
    --sidebar-accent-foreground: oklch(0.33 0.08 150); /* ฟาร์ม: = brand-ink */
    --sidebar-border: oklch(0.922 0 0);
    --sidebar-ring: oklch(0.62 0.11 150); /* ฟาร์ม */
}
```

## Typography

**ฟอนต์เดียวของแอป:** IBM Plex Sans Thai Looped (แบบมีหัว) ทั้งไทยและตัวเลข
ตัวอักษรไทยแบบมีหัวแยก ภ/ถ, ด/ต, บ/ป ได้ง่ายกว่าแบบไม่มีหัว ซึ่งช่วยมากเมื่อตัวเล็กหรือสายตาเริ่มยาว

อักษรไทยดูเล็กกว่าละตินที่ขนาดเดียวกัน เพราะหัวและตัวอักษรอยู่ในพื้นที่ x-height ที่เตี้ยกว่า
จึงตั้งขนาดบนมือถือใหญ่กว่าค่าปกติของเว็บหนึ่งขั้น

### Hierarchy

| บทบาท | มือถือ (< 768px) | เดสก์ท็อป (≥ 768px) | น้ำหนัก | line-height | Tailwind |
| --- | --- | --- | --- | --- | --- |
| **Key number** ตัวเลขสำคัญ (กำไร, ผลผลิตรอบนี้) | 30px | 30px | 600 | 1.3 | `text-3xl leading-[1.3] font-semibold` |
| **Page title** ชื่อจอ `h1` | 24px | 24px | 600 | 1.4 | `text-2xl font-semibold` |
| **Section title** หัวข้อส่วน / ชื่อการ์ด | 20px | 18px | 600 | 1.5 | `text-xl md:text-lg font-semibold` |
| **Body** เนื้อความ, แถวรายการ, ตัวเลขในแถว | **18px** | 16px | 400 | 1.7 | `text-lg md:text-base` |
| **Control** ปุ่ม, แท็บ, เมนู | 18px | 16px | 500 | 1.5 | `text-lg md:text-base font-medium` |
| **Field** ช่องกรอก (input, select, textarea) | 18px | 16px | 400 | 1.5 | `text-lg md:text-base` |
| **Label** ป้ายชื่อเหนือช่อง, บรรทัดรอง, หน่วย | 16px | 14px | 500 / 400 | 1.6 | `text-base md:text-sm` |
| **Meta** วันที่ในแถว, ข้อความเล็กที่สุด | 16px | 14px | 400 | 1.6 | `text-base md:text-sm` |

น้ำหนักที่ใช้มีสามระดับ: 400 (เนื้อความ), 500 (ป้ายชื่อ, ปุ่ม), 600 (หัวข้อ, ตัวเลขสำคัญ)

**The 16px Floor Rule.** บนมือถือไม่มีตัวอักษรเล็กกว่า 16px และบนเดสก์ท็อปไม่มีอะไรเล็กกว่า 14px
ห้ามใช้ `text-xs` (12px) กับข้อความที่ต้องอ่าน ช่องกรอกต้องไม่ต่ำกว่า 16px บนมือถือ ไม่งั้น iOS จะซูมหน้าจอเมื่อแตะช่อง

**The Thai Line Height Rule.** line-height เนื้อความ 1.7 หัวข้อ 1.4 ห้ามต่ำกว่า 1.4
เพราะสระบน วรรณยุกต์ และสระล่าง (เช่น ญ ฐ ฤ ู) จะชนบรรทัดข้างเคียงหรือถูกตัด

**The No Thin Strokes Rule.** ห้ามใช้น้ำหนักต่ำกว่า 400 เพราะเส้นบางอ่านยากกลางแดด
และ `letter-spacing` เป็น 0 เสมอ ห้ามบีบตัวอักษรไทย

**The Steady Numbers Rule.** ตัวเลขใช้ `tabular-nums` (ตั้งไว้ที่ `body`) ตัวเลขในแถวชิดขวา
ตัดคำด้วย `truncate` ได้เฉพาะชื่อที่ยาวผิดปกติ ห้ามตัดตัวเลข

หนึ่งบรรทัดยาวไม่เกินราว 60 ตัวอักษรไทยบนเดสก์ท็อป

### การตั้งค่าใน CSS

```css
@theme {
    --font-sans: 'IBM Plex Sans Thai Looped', ui-sans-serif, system-ui, sans-serif;
}

@layer base {
    html {
        font-size: 100%; /* เคารพขนาดตัวอักษรที่ผู้ใช้ตั้งในเครื่อง */
    }

    body {
        @apply bg-background text-foreground text-lg leading-[1.7] md:text-base;
        font-variant-numeric: tabular-nums;
    }
}
```

ใช้หน่วย `rem` (คลาสของ Tailwind) ไม่ใช้ `px` ตายตัว ถ้าผู้ใช้ขยายตัวอักษรในโทรศัพท์ แอปจะใหญ่ตาม

### รูปแบบตัวเลขและวันที่

| ชนิด | รูปแบบ | ตัวอย่าง |
| --- | --- | --- |
| น้ำหนัก | ทศนิยม 2 ตำแหน่ง, คั่นหลักพัน | `1,192.97 กก.` |
| เงิน | ทศนิยม 2 ตำแหน่ง, คั่นหลักพัน | `34,208.41 บาท` |
| นับจำนวน | ไม่มีทศนิยม | `20 ต้น` |
| วันที่ | วัน เดือนย่อ ปี พ.ศ. | `19 ต.ค. 2569` |
| ศูนย์จริง | พิมพ์ศูนย์ | `0 กก.` |
| ไม่มีข้อมูล | ขีด สีเทา | `—` |

หน่วย (กก., บาท, ต้น) ใช้ `text-muted-foreground` ส่วนตัวเลขใช้ `text-foreground`

## Layout

เดสก์ท็อปเป็นจอหลัก ออกแบบหน้าใหม่สำหรับจอกว้างก่อน แล้วค่อยย่อลงให้อ่านได้บนมือถือ
ใช้ตารางและหลายคอลัมน์บนเดสก์ท็อปได้ แต่บนมือถือตารางต้องเปลี่ยนเป็นแถวหรือการ์ด ห้ามล้นจอ

มือถือ: ขอบนอกจอ 16px (`px-4`) เนื้อหาเรียงคอลัมน์เดียว
เมนูอยู่ล่างจอ (`AppBottomNav`) สูง 64px บวก safe area และเนื้อหามี padding ล่างเท่ากันเพื่อไม่ให้ถูกบัง
header บนมือถือสูง 48px ติดบนจอ

เดสก์ท็อป (≥ 768px) ใช้ sidebar ของ shadcn กว้าง `16rem` และเนื้อหากว้างสูงสุด `max-w-6xl`
จอรายละเอียดที่มีสรุปตัวเลข (สวน, ต้น, ค่าใช้จ่าย) ใช้สองคอลัมน์ตั้งแต่ `lg`
โดยกล่องสรุปอยู่คอลัมน์ขวาและติดบนจอ (`lg:sticky lg:top-6`)

ระยะห่างยึดสเกล Tailwind ทีละ 4px ค่าที่ใช้บ่อยคือ 8, 12, 16, 24px
ปุ่มที่อยู่ติดกันห่างกันอย่างน้อย 8px

**The Thumb Rule.** ปุ่ม ช่องกรอก แถวที่กดได้ และ checkbox มีพื้นที่แตะอย่างน้อย **48 × 48px** บนมือถือ
(`h-12`; เดสก์ท็อปใช้ขนาดเริ่มต้นของ shadcn `h-9` ได้)

**The Sticky Save Rule.** ปุ่มบันทึกของฟอร์มกว้างเต็มจอและติดล่างจอเหนือเมนูล่าง
(`sticky bottom-[calc(4rem+env(safe-area-inset-bottom))]`, บนเดสก์ท็อป `md:bottom-0`) บนแถบพื้นขาวที่มีเส้นขอบบน

**The 390px Rule.** ป้ายในแท็บ ปุ่ม และช่องค้นหาต้องไม่ถูกตัดที่ความกว้าง 390px
ใช้คำสั้น เช่น "รอบก่อน" แทน "รอบที่ผ่านมา", "ค้นหาต้น"

## Elevation & Depth

ผิวแบนทั้งหมด ลำดับชั้นมาจากเส้นขอบบาง (`border`) และความต่างของพื้น (`bg-card`, `bg-muted`, `bg-brand-soft`)
ไม่ใช้เงา ไม่ใช้ไล่สี ไม่ใช้กระจกโปร่ง

ชั้นที่ซ้อนจริง (dialog, sheet, dropdown, toast) ใช้เงาและ overlay ตามค่าเริ่มต้นของ shadcn ได้
toast อยู่ด้านบนกลางจอ (`top-center`) เพื่อไม่ให้บังปุ่มบันทึกที่ติดล่างจอ

ส่วนของจอใช้ `<section className="rounded-lg border bg-card">` **ไม่ใช้ `Card` ของ shadcn**
เพราะ `Card` มี `shadow-sm` และมุม `rounded-xl` ที่ใหญ่กว่าส่วนอื่นของแอป
`shadow-xs` ในปุ่มและช่องกรอกเป็นค่าเริ่มต้นของ shadcn ที่จางจนแทบมองไม่เห็น ปล่อยไว้ได้ แต่ไม่เพิ่มเงาในงานใหม่

**The Flat Field Rule.** ไม่มีเงาบนพื้นที่ที่วางนิ่ง ถ้าต้องแยกส่วน ใช้เส้นขอบหรือพื้นเทา

## Shapes

มุมยึดค่าเริ่มต้นของ shadcn: `--radius` = 10px
`rounded-lg` = 10px (ส่วนของจอ, กล่องตัวเลข, empty state), `rounded-md` = 8px (ปุ่ม, ช่องกรอก), `rounded-sm` = 6px
ทรงแคปซูล (`rounded-full`) ใช้เฉพาะแท่งข้อมูลและแคปซูลหลังไอคอนเมนูล่าง

ไม่ซ้อนกล่องโค้งหลายชั้น ส่วนหนึ่งของจอเป็นกล่องเดียว แถวข้างในแบ่งด้วยเส้น

## Components

ใช้คอมโพเนนต์จาก shadcn/ui (`resources/js/components/ui/`) ตามที่ติดตั้งมา **ไม่ปรับแต่งสไตล์** (สี, เงา, มุม, ระยะ)
นอกจากที่ไฟล์นี้ระบุ คอมโพเนนต์ที่ยังไม่มีให้เพิ่มด้วย shadcn CLI ไม่เขียนเองถ้า shadcn มีอยู่แล้ว

คอมโพเนนต์ของฟาร์ม (`resources/js/components/farm/`) ประกอบจากคอมโพเนนต์ shadcn
ใช้ variant ที่มีอยู่ (`default`, `outline`, `secondary`, `ghost`, `destructive`, `link`) ไม่สร้าง variant ใหม่

**The Only Exceptions Rule.** ส่วนที่แก้จากค่าเริ่มต้นใน `components/ui/` มีเฉพาะขนาดตัวอักษรและพื้นที่แตะ
(เช่น `h-12 md:h-9`, `text-lg md:text-base`) กับตำแหน่ง toast (`top-center`) ส่วน token ที่แก้มีเฉพาะที่ระบุใน §Colors

### Buttons
- สูง 48px บนมือถือ (`h-12`) และ 36px บนเดสก์ท็อป (`md:h-9`) ปุ่มไอคอน `size-12 md:size-9`
- ปุ่มหลัก (`default`) หนึ่งปุ่มต่อจอ ปุ่มอื่นใช้ `outline`, `secondary` หรือ `ghost`
- `destructive` ใช้กับการลบเท่านั้น และต้องยืนยันด้วย `confirm()` จาก `components/confirm-dialog.tsx` ก่อนทุกครั้ง
- ปุ่มบันทึกของฟอร์มใช้ `SubmitButton`: กว้างเต็ม แสดง spinner และปิดการกดระหว่างส่ง ข้อความเริ่มต้น "บันทึก"

### Inputs and Fields
- สูง 48px บนมือถือ ตัวอักษร 18px ขอบ `--input` ที่เข้มกว่าเส้นแบ่ง
- ป้ายชื่ออยู่เหนือช่อง (`Label`, `text-base md:text-sm font-medium`) ข้อผิดพลาดอยู่ใต้ช่องด้วย `InputError`
- focus ใช้ ring สีเขียว invalid ใช้ขอบ `destructive`

### Key Number Box
- กล่องตัวเลขสำคัญของจอ (ผลผลิตรอบนี้, กำไรรอบนี้) พื้น `bg-brand-soft` ตัวหนังสือ `text-brand-ink`
  ตัวเลข `text-3xl leading-[1.3] font-semibold`
- ค่าใช้จ่ายรวมใช้พื้น `bg-muted` แทน และกำไรที่ติดลบใช้ `text-destructive`

### Share Bar
- `ShareBar` สูง 6px (`h-1.5`) รางเต็มความกว้าง `bg-brand-track` เนื้อแท่ง `bg-primary` ทรงแคปซูล
- ต้นที่ยังไม่เก็บในรอบที่เปิดอยู่แสดงรางเปล่า ให้เห็นว่าเหลือกี่ต้น

### Section Tabs
- `SectionTabs` เป็นแท็บเส้นใต้บนเส้นเต็มความกว้าง แท็บที่เลือกมีเส้นใต้ `border-primary` และตัวหนังสือ `text-foreground`
- สูง 48px บนมือถือ แถวแท็บเลื่อนแนวนอนแทนการขึ้นบรรทัดใหม่ จำนวนรายการต่อท้ายป้ายด้วย `text-muted-foreground`

### Selectable Items
- รายการที่เลือกได้หลายตัว (เช่น เลือกต้นไม้) ที่เลือกแล้วใช้ขอบ `border-primary` บนพื้น `bg-brand-soft`
  และ checkbox ใช้ `accent-primary`

### Lists and Rows
- แถวรายการใช้ตัวอักษร Body ชื่ออยู่ซ้าย ตัวเลขชิดขวาด้วย `text-foreground` หน่วยตามด้วย `text-muted-foreground`
- การกระทำต่อแถวอยู่ในเมนูสามจุด (`RowActions`) หนึ่งเมนูต่อแถว: แก้ไขอยู่บนสุด ลบอยู่ล่างสุดเป็นสีแดง คั่นด้วยเส้น
  เพื่อไม่ให้การลบอยู่ห่างแค่การแตะพลาดครั้งเดียว ไม่วางปุ่มหลายปุ่มในแถว

### Tables
- หน้าบันทึกใช้ `Table` ของ shadcn บนเดสก์ท็อป (`md` ขึ้นไป) ตัวอักษร Body ส่วนหัวคอลัมน์ `font-medium` ตัวเลขชิดขวาและกว้างเท่ากัน (`tabular-nums`)
  ตารางอยู่ในกรอบ `rounded-lg border bg-card` และคอลัมน์สุดท้ายเป็น `RowActions`
- ต่ำกว่า `md` ตารางต้องไม่แสดง ให้เปลี่ยนเป็นแถวตามกฎ "Lists and Rows" (ชื่อกับตัวเลขอยู่บรรทัดเดียวกัน ใต้ลงมาเป็นวันที่ สวน และคนที่จด) เพื่อไม่ให้ต้อง scroll แนวนอน
- ป้าย `AiBadge` อยู่ติดชื่อคนที่จดทั้งในตารางและในแถว

### Empty States
- `EmptyState` คงกรอบของส่วนนั้นไว้: หนึ่งประโยคในกล่องเส้นประ `border-dashed border-input` พร้อมการกระทำที่เติมข้อมูลส่วนนั้น
- ไม่ใช้ภาพประกอบหรือไอคอนขนาดใหญ่

### Badges and Attention
- `Badge` ใช้กับป้ายสั้นหนึ่งถึงสองคำเท่านั้น ข้อความในป้ายต้องอ่านเข้าใจได้โดยไม่ต้องดูสี
  - `default` (เขียวเต็ม): สิ่งที่ต้องทำวันนี้ เช่น "ถึงกำหนดวันนี้", "เลยกำหนด"
  - `secondary` (เทา): สถานะเฉย ๆ ของสิ่งของ เช่น ต้นที่ตายหรือถูกตัด, "เจ้าของ", "ปิดใช้งาน"
  - `outline`: บอกที่มาของบันทึก เช่น "AI · แม่"
  - ไม่ใช้ `destructive` กับป้าย สีแดงสงวนไว้ให้การลบและตัวเลขติดลบ
- **แถวที่ต้องดูวันนี้** (ปัญหาที่ถึงกำหนดดูซ้ำ) ใช้พื้นแถว `bg-brand-soft` คู่กับป้าย `default` และอยู่บนสุดของรายการ
  เป็นสถานะ "ต้องสนใจ" สถานะเดียวของแอป

**The No Warning Color Rule.** แอปไม่มีสีเหลือง ส้ม หรือสีเตือนแยก สิ่งที่ต้องสนใจใช้บันไดสีเขียวกับข้อความที่บอกตรง ๆ
ถ้าวันหนึ่งต้องมีระดับความเร่งด่วนมากกว่าหนึ่งระดับ ให้แก้ไฟล์นี้ก่อนเพิ่มสี

### Icons
- ใช้ `lucide-react` ชุดเดียว ไอคอนเป็นสีเดียวกับข้อความข้าง ๆ หรือ `text-muted-foreground`
- ขนาด: ในปุ่มและช่องกรอก `size-4` (ค่าเริ่มต้นของปุ่ม), ลูกศรท้ายแถวที่กดไปหน้าอื่น `size-5`, เมนูล่าง `size-6`
- ไอคอนไม่อยู่ตัวเดียวโดยไม่มีคำ ยกเว้นปุ่มไอคอนที่รู้กันทั่วไป (เมนูสามจุด, ปิด, แสดงรหัสผ่าน) ซึ่งต้องมี `aria-label` ภาษาไทย
- ไอคอนตกแต่งใส่ `aria-hidden` หรือปล่อยให้อยู่ในปุ่มที่มีข้อความแล้ว

### Photos
- รูปจากการสำรวจต้นแสดงเป็นรูปย่อ `size-16 rounded-md border object-cover` เรียงแถว `flex flex-wrap gap-2` ใต้บันทึกนั้น
- แตะรูปย่อเพื่อเปิดรูปเต็มในแท็บใหม่ ลิงก์มี `aria-label` เช่น "เปิดรูปที่ 1 ของ …" และรูปใช้ `alt=""` กับ `loading="lazy"`
- รูปไม่ใช่ที่เก็บข้อมูล อาการหรือตัวเลขที่สำคัญต้องเขียนเป็นข้อความในบันทึกด้วย

### Forms
- **ฟอร์มสั้น** ที่แก้ของสิ่งเดียว (เพิ่มสวน, แก้ต้น, บันทึกค่าใช้จ่าย, เก็บผลต้นเดียว, บันทึกสภาพต้น) อยู่ใน `Dialog`
  ปุ่ม `SubmitButton` อยู่ท้ายฟอร์ม ปิด dialog เมื่อบันทึกสำเร็จ
- **ฟอร์มยาว** ที่ทำกับหลายต้นพร้อมกัน (เก็บผลหลายต้น, บันทึกการดูแลหลายต้น) เป็นหน้าเต็ม และใช้ The Sticky Save Rule
- ช่องเรียงคอลัมน์เดียว ห่างกัน `space-y-4` ป้ายชื่ออยู่เหนือช่อง ช่องที่ไม่บังคับต่อท้ายป้ายด้วย "(ไม่บังคับ)" ใน `text-muted-foreground`
- ค่าเริ่มต้นที่เดาได้ให้ใส่ไว้ก่อน เช่น วันที่เป็นวันนี้ สวนเป็นสวนที่เปิดอยู่ เพื่อให้กรอกน้อยที่สุด
- ช่องตัวเลขใช้ `inputMode="decimal"` หรือ `"numeric"` ให้มือถือเปิดแป้นตัวเลข

### Feedback States
- **กำลังส่ง:** `SubmitButton` แสดง `Spinner` และปิดการกด ไม่ปิดทั้งฟอร์ม
- **กำลังเปลี่ยนหน้า:** แถบโหลดของ Inertia ด้านบนจอสีเทา (`#4B5563`) ไม่ใช้ skeleton เพราะข้อมูลมากับหน้า
  ถ้าวันหนึ่งใช้ deferred props ให้ใช้ `Skeleton` ที่มีรูปร่างเหมือนเนื้อหาจริง
- **สำเร็จ:** toast ด้านบนกลางจอจาก flash ของ backend (`Inertia::flash('toast', …)`) ข้อความสั้น ลงท้ายด้วย "แล้ว"
  เช่น "บันทึกการดูแลแล้ว" ไม่แสดง toast เมื่อผลเห็นได้บนจออยู่แล้วโดยไม่มีอะไรหายไป
- **ผิดพลาดที่ช่อง:** `InputError` ใต้ช่องนั้น ข้อความบอกวิธีแก้ ไม่ใช่แค่บอกว่าผิด
- **ผิดพลาดทั้งฟอร์มหรือทั้งหน้า:** `AlertError` เหนือฟอร์ม หัวข้อเริ่มต้น "เกิดข้อผิดพลาด"
- **ว่าง:** `EmptyState` (ดูด้านบน)
- **คัดลอก:** ปุ่มคัดลอกเปลี่ยนข้อความเป็น "คัดลอกแล้ว" ชั่วครู่ แทนการเปิด toast

### Data Display and Charts
- สัดส่วนและการเปรียบเทียบใช้ `ShareBar` ในแถว ไม่ใช้กราฟวงกลมหรือกราฟแท่งแยก
  เพราะอ่านคู่กับชื่อและตัวเลขได้ทันทีบนจอแคบ
- ตัวเลขจริงอยู่ข้างแท่งเสมอ แท่งเป็นเพียงภาพประกอบ (`aria-hidden`)
- ถ้าต้องมีกราฟจริง (เช่น ผลผลิตเทียบหลายฤดู) ให้แก้ไฟล์นี้ก่อน: ชุดข้อมูลเดียวใช้ `chart-1` (= primary)
  หลายชุดใช้ `chart-1` ถึง `chart-5` ตามลำดับ และต้องมีตารางหรือตัวเลขกำกับ

### Navigation
- **มือถือ:** `AppBottomNav` แสดงทุกปลายทางในหนึ่งแตะ แต่ละช่องสูง 64px ไอคอน 24px
  รายการที่เลือกมีแคปซูล `bg-brand-soft` (56 × 32px) หลังไอคอน ไอคอนเป็น `text-primary` และป้ายเป็น `font-medium text-foreground`
- **เดสก์ท็อป:** sidebar ของ shadcn รายการที่เลือกใช้ `sidebar-accent` (= `brand-soft`) กับ `sidebar-accent-foreground` (= `brand-ink`)
- ลิงก์นำทางใช้ `prefetch` และ `aria-current="page"` กับรายการที่เลือก

### Motion
- ใช้เฉพาะ `transition-colors` ของ hover/active และ animation ของ shadcn (dialog, sheet, toast)
- ไม่มี animation ตกแต่ง เคารพ `prefers-reduced-motion`

## Do's and Don'ts

### Do:
- **Do** ปรับ token ใน `resources/css/app.css` และไฟล์นี้ให้ตรงกันทุกครั้งที่เปลี่ยนสีหรือตัวอักษร
- **Do** ใช้คอมโพเนนต์ shadcn ที่มีอยู่ก่อน และเพิ่มด้วย shadcn CLI เมื่อยังไม่มี
- **Do** ใส่ขนาดสองระดับให้ทุกข้อความและทุกปุ่ม (`text-lg md:text-base`, `h-12 md:h-9`)
- **Do** ใช้ `text-foreground` กับข้อมูลที่ต้องอ่าน และ `text-muted-foreground` กับหน่วย วันที่ ป้ายชื่อ
- **Do** ตรวจทุกจอที่ความกว้าง 390px ว่าไม่มีป้ายถูกตัดและไม่มี scroll แนวนอน
- **Do** เขียนข้อความทุกอย่างเป็นภาษาไทย รวมถึง `aria-label` และ `sr-only`

### Don't:
- **Don't** เพิ่มสีที่สอง ไล่สี หรือสีสถานะใหม่ เมื่อบทบาทนั้นใช้บันไดสีเขียว เทา หรือ `destructive` ได้
- **Don't** ใช้ `text-xs` หรือสีเทาที่อ่อนกว่า `--muted-foreground` กับข้อความ
- **Don't** ใช้น้ำหนักต่ำกว่า 400, `letter-spacing` ที่ไม่ใช่ 0 หรือ line-height ต่ำกว่า 1.4 กับภาษาไทย
- **Don't** สร้าง variant ใหม่ของคอมโพเนนต์ shadcn หรือแก้สี เงา มุม ระยะ ใน `components/ui/`
- **Don't** ใส่เงาบนพื้นที่ที่วางนิ่ง หรือซ้อนกล่องโค้งหลายชั้น
- **Don't** ใช้สีเขียวเพื่อบอกว่า "ดี" หรือใช้สีแดงกับสิ่งที่ไม่ใช่การลบหรือตัวเลขติดลบ
- **Don't** วางปุ่มหลักมากกว่าหนึ่งปุ่มในหนึ่งจอ
