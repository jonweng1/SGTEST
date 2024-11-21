<?php
use \Vimeo\Controller\ModworldV6\RapSheetController;

?>
<style>
        section {
            position: relative;
            padding: 0.5rem 0.25rem;
        }

        h1 {
            margin-top: 1rem;
        }

        ul {
            max-width: 350px;
        }

        .phrase-item {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
            padding: 0.5rem 0.25rem;
        }
        .phrase-item:nth-child(odd) {
            background-color: #C0C2C9;
            color: #000000;
        }
        .phrase-item button {
            margin-top: 0;
        }

        .toast {
            position: absolute;
            top: 0;
            left: 50%;
            padding: 0.5rem 0.25rem;
            transform: translateX(-50%);
        }
        .error {
            background-color: #FFCCCC;
        }
</style>
<main>
    <?= (new RapSheetController())->renderView('rap_sheet_menu', array(
        'page' => 'transcript_phrases',
        'user' => $user,
    )) ?>
    <section>
        <section>
            <h1>Transcript Phrases</h1>
            <p><b>Must be on VPN to use this tool.</b></p>
            <p>Add phrases to a users transcript phrases dictionary. You input a comma delimited list of phrases. A user can have a maximum of 50 Phrases.</p>
            <p>Phrases may only be 34 characters long, and can only contain Alphabet and punctuation. No numbers or symbols.</p>
            <div class="content">
                <form id="transcript-phrase-form">
                    <input type="text" name="phrases" placeholder="Add Phrases"/>
                    <button>Submit</button>
                </form>
            </div>
        </section>
        <section>
            <h2><?= $user->display_name ?>'s Transcript Phrases</h2>
            <div class="loader"><p>Loading...</p></div>
            <ul id="phrase-list"></ul>
        </section>
        <div id="error-toast" class="toast error hidden"></div>
    </section>
</main>
<script>
    (async function() {
        const userJwt = 'jwt <?= $user_jwt ?>';
        const apiHost = '<?= API_HTTPS_URL ?>';
        const transcriptPhraseRoute = '<?= API_HTTPS_URL ?>/users/<?= $user->id ?>/transcript_phrases'
        const form = document.getElementById('transcript-phrase-form');
        const phraseList = document.getElementById('phrase-list');
        const loader = document.querySelector('.loader');
        const errorToast = document.getElementById('error-toast');

        /**
         * Phrases api utilities
         */

        async function fetchPhrases() {
            const resp = await fetch(transcriptPhraseRoute, {
                headers: {
                    'Authorization': userJwt,
                }
            });
            const body = await resp.json();

            if (body.error) {
                throw new Error(body.error);
            }

            return body;
        }

        async function postPhrases(phrases) {
            const requests = phrases.map((phrase, i) => {
                return new Promise((resolve, reject) => {
                    setTimeout(async () => {
                        try {
                            const resp = await fetch(transcriptPhraseRoute, {
                                method: 'POST',
                                headers: {
                                    'Authorization': userJwt,
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ phrase }),
                            });
                            const body = await resp.json();
                            if (body.error) {
                                throw new Error(body.error);
                            }
                            console.log('body', body);
                            resolve(body);
                        } catch (e) {
                            console.log('catch e', e);
                            reject(e);
                        }
                    }, i * 100);
                });
            });

            return Promise.all(requests);
        };

        async function deletePhrase(phraseUri) {
            const resp = await fetch(`${apiHost}${phraseUri}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': userJwt,
                }
            });
            const body = resp.json();
            if (body.error) {
                throw new Error(body.error);
            }
        }

        /**
         * DOM utilities
         */

        function toggleHidden(el, force) {
            el.classList.toggle('hidden', force);
        }

        function setErrorMessage(error) {
            errorToast.appendChild(document.createTextNode(error.message));
            toggleHidden(errorToast, false);
        }

        function renderPhrases(phrases) {
            for (let phraseItem of phrases) {
                const li = document.createElement('li');
                li.classList.add('phrase-item');

                const p = document.createElement('p');
                p.appendChild(document.createTextNode(phraseItem.phrase));

                const deleteButton = document.createElement('button');
                deleteButton.appendChild(document.createTextNode("Delete"));
                deleteButton.classList.add('delete');
                deleteButton.addEventListener('click', async () => {
                    try {
                        toggleHidden(loader, false);
                        await deletePhrase(phraseItem.uri);
                        toggleHidden(loader, true);
                        li.remove();
                    } catch (e) {
                        setErrorMessage(e);
                    }
                })


                li.appendChild(p);
                li.appendChild(deleteButton);
                phraseList.appendChild(li);
            }
        }

        async function refresh() {
            toggleHidden(loader, false);
            try {
                const response = await fetchPhrases();
                toggleHidden(loader, true);
                renderPhrases(response.data);
            } catch(e) {
                setErrorMessage(e);
            }

        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const phrases = formData.get('phrases').split(',');
            try {
                toggleHidden(errorToast, true);
                toggleHidden(loader, false);
                const createdPhrases = await postPhrases(phrases);
                toggleHidden(loader, true);
                renderPhrases(createdPhrases);
            } catch (e) {
                setErrorMessage(e);
            }
            form.reset();
        });


        await refresh();
    })();
</script>
