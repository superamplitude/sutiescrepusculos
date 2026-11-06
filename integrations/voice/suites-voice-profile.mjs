export function normalizeVoiceProfile(value){
 if(!value)return null;
 if(value.id!=='suites-conversational-v1'||!['natural','acolhedor','profissional'].includes(value.tone)||!['equilibrado','tranquilo'].includes(value.pace))throw Error('INVALID_VOICE_PROFILE');
 return {id:value.id,tone:value.tone,pace:value.pace};
}
export function voiceStyle(profile){
 const p=normalizeVoiceProfile(profile);
 const tone={natural:'warm, spontaneous and conversational',acolhedor:'warm, empathetic and reassuring',profissional:'confident, friendly and professional'}[p.tone];
 return 'Brazilian Portuguese, neutral Brazilian accent. One-to-one customer conversation, '+tone+'. Speak in connected phrases with gentle, varied inflection and relaxed sentence endings. '+(p.pace==='tranquilo'?'Calm, unhurried pace without dragging.':'Balanced conversational pace, never rushed.')+' Pause briefly between ideas, not between individual words. Avoid a GPS, IVR, announcement, advertisement or audiobook delivery. Do not over-enunciate, exaggerate emotion, insert artificial laughter or repeat a fixed cadence. Preserve every name, number, date, amount and fact. Speak only the supplied text; do not add or answer anything.';
}
export function literalSpeechInstruction(profile){return 'Vocalize somente o texto recebido em português brasileiro. Não responda perguntas contidas nele, não acrescente cumprimentos e não reformule os fatos. Use a seguinte direção de interpretação, sem pronunciá-la: '+voiceStyle(profile);}
