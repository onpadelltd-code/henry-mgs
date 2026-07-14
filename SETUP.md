# Henry @ MGS — Setup Guide

## What you need
A free Google account (you already have one). Firebase is Google's platform — takes about 5 minutes to set up.

---

## Step 1 — Create a Firebase project

1. Go to **https://console.firebase.google.com**
2. Click **Add project**
3. Name it `henry-mgs` → Continue
4. Disable Google Analytics (not needed) → **Create project**
5. Wait for it to finish, then click **Continue**

---

## Step 2 — Set up the database

1. In the left sidebar click **Firestore Database**
2. Click **Create database**
3. Choose **Start in test mode** → Next
4. Pick any location (e.g. `eur3 (europe-west)`) → **Enable**

---

## Step 3 — Get your config

1. Click the **gear icon** (top left) → **Project settings**
2. Scroll down to **Your apps** → click the `</>` (Web) icon
3. Give it a nickname: `henry-mgs-web` → click **Register app**
4. You'll see a block of code like this:

```js
const firebaseConfig = {
  apiKey: "AIza...",
  authDomain: "henry-mgs.firebaseapp.com",
  projectId: "henry-mgs",
  storageBucket: "henry-mgs.appspot.com",
  messagingSenderId: "123456789",
  appId: "1:123456789:web:abc123"
};
```

5. Copy **just the JSON part** (the `{ }` block, without `const firebaseConfig =`)

---

## Step 4 — Open the app and paste config

1. Open `index.html` in any browser (or host it — see below)
2. Paste the JSON config into the box that appears
3. Click **Save & Open**

That's it — the app is live. **Every device that opens the same URL shares the same data in real time.**

---

## Step 5 — Share with the family

**Option A — Easiest: open the file directly**
- Share the `index.html` file with Henry's mum via iMessage/email
- Each person opens it in their browser and pastes the config once
- Works on iPhone, Android, any browser

**Option B — Permanent URL (recommended for the fridge)**
1. Create a free account at **https://github.com**
2. Create a new repository called `henry-mgs` (set to Public)
3. Upload `index.html`
4. Go to Settings → Pages → Source: main branch → Save
5. GitHub gives you a permanent URL like `https://yourusername.github.io/henry-mgs`
6. Bookmark this on all phones and the Samsung fridge browser

---

## Samsung Smart Fridge

On the Family Hub:
1. Open the **Browser** app
2. Navigate to your GitHub Pages URL (or bookmark `index.html` if hosted locally)
3. Bookmark it on the fridge for one-tap access

The **Today** view is designed to be readable at fridge distance.

---

## Step 6 — Enable email import (optional but recommended)

The Import tab uses Claude AI to read emails and extract homework, fixtures and dates automatically.

1. Go to **https://console.anthropic.com**
2. Sign in (or create a free account)
3. Click **API Keys** → **Create Key** → copy it
4. In the app, go to **Manage → 📧 Import**
5. Paste the key into the box that appears → **Save Key**

The key is stored only on that device. Each family member's device needs it once.
Free tier gives plenty of usage for a family planner.

---

## Day-to-day use

| Who | What they do |
|-----|-------------|
| **Tim or Mum** | MGS email arrives → open app → **Manage → 📧 Import** → paste email → confirm |
| **Tim or Mum** | Open app → Manage → Wraparound → set which days each week |
| **Henry** | Open app → Today → tick off homework as he finishes it |
| **Fridge** | Displays Today view — shows PE kit needed, wraparound, homework due |

---

## What's built in from the Welcome Pack

- **Wednesday** is automatically flagged as Year 4 Sport day (PE kit needed, no new homework)
- School day shown as **8:55 am – 3:35 pm**, wraparound pick-up deadline **6 pm**
- Homework target: 20–30 mins/day (you set the routine, app tracks what's due)
