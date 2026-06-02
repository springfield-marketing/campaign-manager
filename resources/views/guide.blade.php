<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Application Guide</h2>
            <p class="mt-1 text-sm ui-muted">A plain-English walkthrough of how this system works.</p>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">

            {{-- Quick jump links --}}
            <nav class="ui-card ui-card-pad mt-6">
                <p class="text-xs font-semibold uppercase tracking-wide ui-muted mb-3">Jump to section</p>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <a href="#overview" class="ui-link">Overview</a>
                    <a href="#contacts" class="ui-link">Contacts &amp; Numbers</a>
                    <a href="#ivr" class="ui-link">IVR (Voice Calls)</a>
                    <a href="#whatsapp" class="ui-link">WhatsApp</a>
                    <a href="#number-statuses" class="ui-link">Number Statuses</a>
                    <a href="#suppressions" class="ui-link">Suppressions &amp; Unsubscribers</a>
                    <a href="#reports" class="ui-link">Reports</a>
                    <a href="#glossary" class="ui-link">Glossary</a>
                </div>
            </nav>

            {{-- Overview --}}
            <section id="overview" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">What does this application do?</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    Campaign Tracker is a central hub for managing outreach campaigns across two channels: <strong class="ui-strong">IVR (automated voice calls)</strong> and <strong class="ui-strong">WhatsApp messages</strong>. It holds a database of all client phone numbers, tracks every campaign that has been run against them, and uses that history to decide which numbers are eligible to be contacted again.
                </p>
                <p class="mt-3 text-sm ui-muted leading-relaxed">The main jobs the system handles are:</p>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm ui-muted">
                    <li>Import contacts — upload a CSV file of names and phone numbers to add them to the database.</li>
                    <li>Upload campaign results — after a campaign is run externally, upload the results file here so the system can record who was reached, who missed the call, who replied, etc.</li>
                    <li>Track eligibility — based on call history, the system automatically marks numbers as Active, Inactive, or Dead so you know who can be contacted in the next campaign.</li>
                    <li>Manage unsubscribers — numbers that have opted out are suppressed and excluded from all future campaigns.</li>
                    <li>Reports — see campaign performance, call outcomes, WhatsApp lead interest, and cost summaries.</li>
                </ul>
            </section>

            {{-- Contacts --}}
            <section id="contacts" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">Contacts &amp; Phone Numbers</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    The system stores <strong class="ui-strong">clients</strong> (people) separately from their <strong class="ui-strong">phone numbers</strong>. One client can have multiple phone numbers. Each phone number is tracked independently — it has its own call history, status, and eligibility.
                </p>

                <h4 class="mt-5 font-semibold text-sm ui-strong">How a contact is created</h4>
                <p class="mt-2 text-sm ui-muted leading-relaxed">
                    When you upload a raw import CSV, each row creates (or updates) a client and links the phone number to them. If the phone number already exists in the database, the client record is updated with any missing information, and the import is counted as a duplicate — but no data is lost.
                </p>

                <h4 class="mt-5 font-semibold text-sm ui-strong">What information is stored per number</h4>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm ui-muted">
                    <li>The raw and normalised phone number (e.g. 0526000403 → +971526000403)</li>
                    <li>Whether it is a UAE number</li>
                    <li>Which client it belongs to, and their region/community</li>
                    <li>Full call history (IVR) or message history (WhatsApp)</li>
                    <li>Current eligibility status: Active, Inactive, or Dead</li>
                    <li>Any suppression records (why and when the number was excluded)</li>
                </ul>

                <h4 class="mt-5 font-semibold text-sm ui-strong">Viewing a number</h4>
                <p class="mt-2 text-sm ui-muted leading-relaxed">
                    Go to <strong class="ui-strong">Numbers</strong> in either the IVR or WhatsApp section to search and browse phone numbers. Click any number to open its history page, which shows the client details, all linked numbers for that client, the full call/message history, source history (which imports it came from), and suppression history.
                </p>
            </section>

            {{-- IVR --}}
            <section id="ivr" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">IVR — Automated Voice Calls</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    IVR (Interactive Voice Response) campaigns are automated phone calls where a pre-recorded message is played. The recipient can press a key on their phone to respond (e.g. press 1 to register interest). This section explains the full workflow.
                </p>

                <div class="mt-6 space-y-6">

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 1 — Upload a script (optional but recommended)</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Go to Scripts in the IVR section. Upload the audio file and paste the script text. Give it a clear name (e.g. "June 2026 — Project Launch"). Scripts are stored in a library so they can be reused across multiple campaigns without re-uploading.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 2 — Import your contact list</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Go to Import. Upload a CSV file containing the names and phone numbers you want to dial. The required columns are <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">name</code> and <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">phone</code>. Optional columns include <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">email</code>, <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">city</code>, <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">community</code>, <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">nationality</code>, and <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">gender</code>.
                        </p>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The import processes in the background. You will see a progress bar on the page. Once done, the numbers are added to the database and are ready to be exported for the call centre.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 3 — Run the campaign externally</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Export the eligible numbers from the Numbers page (use the export button with filters applied) and send them to the IVR call centre or platform. The campaign is run outside this application.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 4 — Upload campaign results</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            After the campaign, the call centre sends back a CSV file with the result of each call (Answered, Missed, No Answer, etc.) and the key pressed by the recipient. Go to Campaign Results and upload this file. Select the script that was used, give the campaign an ID, and submit.
                        </p>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The system processes the file and for each number: records the call outcome, updates the number's eligibility status, and calculates the cooldown period before it can be called again.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 5 — Review the results</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Open the campaign from the Campaign Results list to see a breakdown of all call outcomes, the leads (numbers that responded positively), and cost estimates. You can export the leads list as a CSV.
                        </p>
                    </div>

                </div>

                <h4 class="mt-6 font-semibold text-sm ui-strong">Cooldown periods</h4>
                <p class="mt-2 text-sm ui-muted leading-relaxed">
                    After a call, each number is placed on a cooldown — a waiting period before it can be called again. The cooldown length is different depending on whether the call was answered or missed. These durations are configured in IVR Settings. During the cooldown, the number shows as Inactive.
                </p>
            </section>

            {{-- WhatsApp --}}
            <section id="whatsapp" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">WhatsApp</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    The WhatsApp section works similarly to IVR but tracks WhatsApp message campaigns. Contacts are imported from CSV, and campaign result files are uploaded after a campaign is sent through an external platform.
                </p>

                <div class="mt-6 space-y-6">

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 1 — Import your contact list</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Go to WhatsApp → Import. Upload a CSV with <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">name</code> and <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">phone</code> as required columns. The same optional columns (city, community, etc.) are supported. Numbers are added to the shared contacts database — the same database used by IVR.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 2 — Run the campaign externally</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Export the contact list and send it to your WhatsApp messaging platform. The campaign is sent outside this system.
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 3 — Upload campaign results</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            After the campaign, upload the results file via Import → Upload campaign results. The system records delivery status, whether the recipient replied, and whether they expressed interest (a "lead").
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-sm ui-strong">Step 4 — Review results and leads</p>
                        <p class="mt-1 text-sm ui-muted leading-relaxed">
                            Go to Campaign Results to open any campaign and see a breakdown of message delivery, replies, and leads. Leads can be exported to a CSV for the sales team.
                        </p>
                    </div>

                </div>
            </section>

            {{-- Number statuses --}}
            <section id="number-statuses" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">Number Statuses — Active, Inactive, Dead</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    Every phone number in the IVR system has one of four statuses. This status is recalculated automatically after each campaign result upload. You can see the status on the Numbers page and on each number's history page.
                </p>

                <div class="mt-5 space-y-5">

                    <div class="rounded-lg border border-[var(--line)] p-4">
                        <span class="ui-pill ui-pill-active">Active</span>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The number is eligible to be included in the next campaign. It has been called fewer than 3 times in total and is not currently in a cooldown period.
                        </p>
                    </div>

                    <div class="rounded-lg border border-[var(--line)] p-4">
                        <span class="ui-pill">Inactive</span>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The number is temporarily on hold. This happens when the number is within its cooldown window (waiting period after the last call) or has been called 3 or more times in total. Once the cooldown window passes, it becomes Active again — unless it has crossed the Dead threshold.
                        </p>
                    </div>

                    <div class="rounded-lg border border-[var(--line)] p-4">
                        <p class="text-sm font-semibold ui-strong">Dead</p>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The number has had 5 consecutive missed or unanswered calls — meaning no one has picked up in the last 5 attempts in a row. The system treats this as a permanently unreachable number and removes it from future campaigns. If the number is ever answered again, the consecutive miss counter resets.
                        </p>
                    </div>

                    <div class="rounded-lg border border-[var(--line)] p-4">
                        <p class="text-sm font-semibold ui-strong">Unsubscribed (Dead)</p>
                        <p class="mt-2 text-sm ui-muted leading-relaxed">
                            The number has opted out — either by pressing the unsubscribe key during a call, via an unsubscriber import file, or by being manually marked from the number history page. Unsubscribed numbers are always excluded from campaigns, regardless of call history. The unsubscribe can be removed from the number's history page if needed.
                        </p>
                    </div>

                </div>
            </section>

            {{-- Suppressions --}}
            <section id="suppressions" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">Suppressions &amp; Unsubscribers</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    A suppression is a record that says "do not contact this number". When a number is suppressed, it is excluded from campaign exports and is shown as Dead in the Numbers list.
                </p>

                <h4 class="mt-5 font-semibold text-sm ui-strong">How suppressions are created</h4>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm ui-muted">
                    <li>Unsubscriber import — upload a CSV of numbers that have opted out via the Unsubscribers page. Each number in the file gets a suppression record.</li>
                    <li>During campaign result upload — if the results file contains an unsubscribe outcome (e.g. the recipient pressed the unsubscribe key), the system automatically creates a suppression.</li>
                    <li>Manual — from the number history page, use the "Mark as unsubscribed" button to suppress a number individually.</li>
                </ul>

                <h4 class="mt-5 font-semibold text-sm ui-strong">Viewing &amp; removing suppressions</h4>
                <p class="mt-2 text-sm ui-muted leading-relaxed">
                    The Unsubscribers page shows all suppressed numbers. You can also see the full suppression history for any individual number on its history page. To remove a suppression, click "Remove unsubscribe" on the number history page — the number will become eligible again.
                </p>

                <h4 class="mt-5 font-semibold text-sm ui-strong">Important</h4>
                <p class="mt-2 text-sm ui-muted leading-relaxed">
                    Suppression records are kept permanently for audit purposes even after they are released. The history section on each number page shows all past suppressions and when they were released.
                </p>
            </section>

            {{-- Reports --}}
            <section id="reports" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">Reports</h3>
                <p class="mt-3 text-sm ui-muted leading-relaxed">
                    The Reports section provides a summary of campaign performance over time.
                </p>

                <h4 class="mt-5 font-semibold text-sm ui-strong">IVR Reports</h4>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm ui-muted">
                    <li>Total calls made, answered, missed, and unanswered per month</li>
                    <li>Lead count (numbers that pressed a response key)</li>
                    <li>Total call duration in minutes</li>
                    <li>Cost estimate based on the pricing configured in Settings (price per minute, monthly quota)</li>
                    <li>Breakdown by campaign</li>
                </ul>

                <h4 class="mt-5 font-semibold text-sm ui-strong">WhatsApp Reports</h4>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm ui-muted">
                    <li>Total messages sent, delivered, read, and replied per campaign</li>
                    <li>Lead count per campaign</li>
                    <li>Monthly overview</li>
                </ul>
            </section>

            {{-- Glossary --}}
            <section id="glossary" class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title text-lg">Glossary</h3>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold ui-strong">Raw Import</dt>
                        <dd class="mt-1 ui-muted">Uploading a CSV of contact information (names + phone numbers) to add people to the database. This does not run a campaign — it just populates contacts.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Campaign Results</dt>
                        <dd class="mt-1 ui-muted">The output file from an IVR or WhatsApp campaign, containing the outcome for each number dialled or messaged. This is uploaded after the campaign is run.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Lead</dt>
                        <dd class="mt-1 ui-muted">A contact who showed positive interest — for IVR this means they pressed a specific key during the call; for WhatsApp it means they replied indicating interest. Leads are exported for the sales team to follow up.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Cooldown</dt>
                        <dd class="mt-1 ui-muted">A waiting period applied to a number after a call. During this time the number cannot be included in a new campaign. The length is set in IVR Settings and differs for answered vs missed calls.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Normalised Phone</dt>
                        <dd class="mt-1 ui-muted">A phone number converted to a standard international format (e.g. +971526000403). This is how the system identifies unique numbers regardless of how they were entered in the original file.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Suppression</dt>
                        <dd class="mt-1 ui-muted">A record that prevents a number from being contacted. Created when someone unsubscribes or is manually excluded. A suppression can be released, but the history is always kept.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Duplicate</dt>
                        <dd class="mt-1 ui-muted">A row in an import file whose phone number already exists in the database. It is counted separately in the import summary. The client information is updated (if fields are empty) but no second record is created.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">DTMF Outcome</dt>
                        <dd class="mt-1 ui-muted">The key the recipient pressed during an IVR call (e.g. 1 = interested, 9 = unsubscribe). DTMF stands for Dual-Tone Multi-Frequency — it is the technical name for the tones produced by pressing phone keys.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Script</dt>
                        <dd class="mt-1 ui-muted">The audio file and/or text used in an IVR campaign. Scripts are uploaded once in the Scripts library and then selected when uploading campaign results, so you do not need to upload the same audio file multiple times.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold ui-strong">Source</dt>
                        <dd class="mt-1 ui-muted">Where a contact was originally imported from. Every time a number is imported, a source record is created showing the file name, import date, and channel (IVR or WhatsApp). This is visible on the number history page.</dd>
                    </div>
                </dl>
            </section>

        </div>
    </div>
</x-app-layout>
