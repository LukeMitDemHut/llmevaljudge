# Linguistic precision -> DAG

```
Does the answer contain any spelling or typographical errors?
Does the answer contain any grammatical errors (e.g., incorrect gender, verb conjugation, or sentence structure)?
Does the answer avoid unnecessary neologisms or word inventions that are not established in the target language?
Does the answer avoid mixing words or phrases from different languages like adding English vocabulary into the non-English target language, except for widely accepted technical terms?
Does the answer use technical terminology in a correct and domain-appropriate way?
Does the answer maintain a neutral and fact-based tone without expressing personal opinions, preferences, or subjective evaluations of the question?
Does the answer use an appropriately formal and academically distant style (avoiding colloquialisms, direct personal address, or casual phrasing)?
```

# Mediation -> G-EVAL

1. Check if text formatting and paragraphs are used to structure the text.
   Examples for good formatting are:
   - paragraphs
   - headings (#, ##, ###)
   - bullet points (-, \*, +)
   - numbered lists (1., 2., 3.)
   - highlighted text such as `code snippets`
   - bold or italic text (**bold**, *italic*) 

     Examples for bad formatting are:
   - large blocks of unformatted text
   - inconsistent or missing formatting
   - random or excessive use of formatting
2. Check if elements are linked with logical or temporal connectors.
   Examples for good connectors are:
   - logical connectors: therefore, however, in contrast, on the other hand, for example, for instance, in addition, furthermore
   - temporal connectors: first, second, then, next, finally Examples for bad connectors are:
   - missing connectors between sentences or paragraphs
   - incorrect or misleading use of connectors
3. Check if the style of the answer is adequate for students in a university learning context

   The text should support understanding by **adapting language** and **breaking down complexity**.

   Indicators of good style are:  
   - technical or specialized terms are introduced with short explanations (e.g., “X means …”)  
   - difficult content is paraphrased into simpler but still precise language  
   - complex processes are broken into clear steps or sequences  
   - instructions or ideas are presented in a structured way  
   - main points are explicitly highlighted and logically ordered

     
   Indicators of bad style are:  
   - use of technical jargon without explanation  
   - unnecessarily long or complicated sentences without clarification  
   - presenting multi-step processes as one continuous block of text  
   - failure to distinguish main points from details  
   - mixing casual, emotional, or opinionated language into explanatory content
4. Check if the answers depth is adequate for university level explanations. The answer has to involve concrete naming of concepts and technical terms throughout the whole explaination. Punish superficial answers in the overall rating.  Aspects that are important for answering the questions have to be mentioned and explained briefly, naming basic concepts is not enough. Punish if the answer deviates in terms of its depth a lot throughout the whole explanation.

   An example for such deviation is:  
   Photosynthesis is the anabolic, endergonic process by which photoautotrophs (e.g. plants, algae, cyanobacteria) convert light energy into chemical energy, producing molecules. 

   Here in this explanation it starts by explaining the concept in technical terms being contextualized but follows by stating that it produces molecules without going into detail what kind of molecules and how.
5. Check if the text structure follows a logic that makes the text easy to follow and coherent.

   The text should be segmented into clearly defined sections. 

   The Structure should guide the user from a meaningful overview to detailed explanations.

    An example for a good structure is: INPUT: Explain the concept of photosynthesis. OUTPUT: # Photosynthesis: Advanced Overview ## Definition

   ```
       Photosynthesis is the anabolic, endergonic process by which photoautotrophs (e.g. plants, algae, cyanobacteria) convert light energy into chemical energy, producing organic molecules (e.g. glucose) from CO₂ and H₂O, with O₂ as a byproduct.
   
       ## General Reaction
       6CO2​+6H2​O+light→C6​H12​O6​+6O2​
       But this is the overall net reaction. Mechanistically, it is split into two stages:
   
       ### Two Stages of Photosynthesis
   
       1. Light-dependent Reactions (in the thylakoid membranes)
       Inputs:
       - Light
       - H₂O
       - NADP⁺
       - ADP + Pi
   
       Outputs:
       - ATP
       - NADPH
       - O₂
   
       Process:
   
       Photosystems I and II (PSI & PSII): Large protein-pigment complexes that absorb photons.
   
       Photolysis of water: Occurs at PSII → releases electrons, protons, and O₂.
   
       Electron Transport Chain (ETC): Electrons move from PSII → plastoquinone (PQ) → cytochrome b6f → plastocyanin (PC) → PSI.
   
       Proton Gradient: Built up in the thylakoid lumen, driving ATP synthesis via ATP synthase (chemiosmosis).
   
       NADP⁺ reduction: PSI transfers electrons to ferredoxin, which reduces NADP⁺ to NADPH via ferredoxin-NADP⁺ reductase (FNR).
   
       ### Summary:
       Light energy → ATP + NADPH (stored energy for the Calvin cycle)
       Oxygen is a byproduct of water splitting.
   
       2. Light-independent Reactions (Calvin-Benson Cycle) (in the stroma)
       Inputs:
       - CO₂
       - ATP
       - NADPH
       Outputs:
       - G3P (glyceraldehyde-3-phosphate) → used to form glucose and other carbohydrates
   
       Phases:
       a. Carbon Fixation
   
       Enzyme: Rubisco (Ribulose-1,5-bisphosphate carboxylase/oxygenase)
   
       Reaction: [....]
   
       b. [...]
   ```
6. Punish scattered explanations that jump between topics very hard.
   An example for a scattered explaination would be an answer that has the following structure:
   INPUT: Explain the concept of photosynthesis.
   OUTPUT: [structure] Start with the Calvin Cycle (Dark Reactions)
   [structure] Jump to Water Splitting and Oxygen Evolution
   [structure] Briefly Mention Light Reactions as Background Noise
   [structure] Circle Back to Calvin Cycle Outputs
   [structure] End with Vague Summary
   This would be very confusing for the reader.
7. Give a small reward for the use of relevant definitions, examples, analogies, or metaphors that help clarify complex ideas.

   Good examples are:

   \-  Definitions that are concise

   \- Examples that are relevant to the topic and help illustrate the concept.

   \- Analogies that relate closely to the concept being explained.

   \- Metaphors that provide a clear mental image of the concept.

   Bad examples are:

   \- Examples that deviate from the topic and confuse the reader.

   \- Analogies that are too far-fetched or unrelated to the concept.

   \- Metaphors that are unclear or misleading.

# Correctness and Completeness -> TALE

Evaluate the Correctness and Completeness of the Response.

Consider:

- Factual Accuracy: Are the facts presented in the answer correct and verifiable with the results from the context or collected evidence?
- Punish minor inaccuracies lightly.
- Punish any factual wrongdoings, contradictions, or misleading statements hard.
- Completeness: Does the answer address all parts of the question or topic comprehensively?
- Reward if all relevant aspects covered to provide a full understanding of the topic.
- Punish if key aspects are missing or if the answer is too superficial.
- Do not reward unnecessary repetition.
- Reward answers that correctly cite or use relevant documents provided in the context

Use the following reference to score the answer:

| SCORE RANGE | Level of correctness                                                                                                                                                                                                |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 0 - 0.2     | Clear factual errors that contradict the sources. Possibly misleading statements.                                                                                                                                   |
| 0.3 - 0.6   | Minor factual inaccuracies. Overall correct answer that misses key parts of the subject.                                                                                                                            |
| 0.7 - 0.9   | Answer is factually fully aligned with the sources. It  covers the important aspects of the subject. Even though the context has documents that should be cited, no references exist to those in the actual output. |
| 1           | Answer is factually fully aligned with the sources. It  covers the important aspects of the subject. Some references to documents in the context exist.                                                             |

# Anhang

Linguistic Precision, Antworten zur Ermittlung der Metrik, Answer First Ansatz, Modellkonfiguration jeweils Standardeinstellungen für jeweiliges Modell in HF Spaces, für Jede Frage wurde ein neuer Chat angelegt

[https://drive.lukekahms.de/f/34043](https://drive.lukekahms.de/f/34043 (preview))