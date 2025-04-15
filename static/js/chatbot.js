const API_KEY = "AIzaSyDzxmO93k2zn7F0vnieYUBHm5c-xTY9ejo"; 
const API_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${API_KEY}`;

// Store the last analysis data from profile
let lastAnalysisData = null;

// Check if message is asking about health report
function isHealthReportQuestion(message) {
  const keywords = ['report', 'health', 'analysis', 'eeg', 'brain', 
                   'alzheimer', 'dementia', 'parkinson', 'sclerosis',
                   'problem', 'issue', 'concern', 'result', 'my data',
                   'condition', 'mental', 'neurological'];
  return keywords.some(keyword => 
    message.toLowerCase().includes(keyword.toLowerCase()));
}

// Extract analysis data from localStorage
function getAnalysisData() {
  try {
    const savedData = localStorage.getItem('eegAnalysisData');
    if (savedData) {
      return JSON.parse(savedData);
    }
  } catch (e) {
    console.error("Error reading analysis data:", e);
  }
  return null;
}

// Generate health summary from analysis data
function generateHealthSummary(analysis) {
  if (!analysis || !analysis.analysis) return null;
  
  let summary = "EEG Frequency Distribution Summary:\n";
  let hasPotentialIssues = false;
  let potentialConditions = [];
  let mostAffectedChannels = {};
  
  Object.entries(analysis.analysis).forEach(([channel, result]) => {
    summary += `\n${channel} Channel:\n`;
    
    // Add frequency distribution
    Object.entries(result.distribution).forEach(([freq, percent]) => {
      summary += `- ${freq}: ${percent.toFixed(1)}%\n`;
    });
    
    // Check for potential conditions
    const { distribution } = result;
    
    // Alzheimer's/Dementia pattern (Theta increase, Alpha decrease)
    if (distribution.Theta > 35 && distribution.Alpha < 30) {
      if (!potentialConditions.includes("Alzheimer's/Dementia")) {
        potentialConditions.push("Alzheimer's/Dementia");
      }
      mostAffectedChannels["Alzheimer's/Dementia"] = (mostAffectedChannels["Alzheimer's/Dementia"] || []).concat(channel);
    }
    
    // Parkinson's pattern (Beta increase)
    if (distribution.Beta > 40 && distribution.Gamma < 15) {
      if (!potentialConditions.includes("Parkinson's")) {
        potentialConditions.push("Parkinson's");
      }
      mostAffectedChannels["Parkinson's"] = (mostAffectedChannels["Parkinson's"] || []).concat(channel);
    }
    
    // Multiple Sclerosis pattern (Gamma abnormalities)
    if (distribution.Gamma > 25 || distribution.Gamma < 5) {
      if (!potentialConditions.includes("Multiple Sclerosis")) {
        potentialConditions.push("Multiple Sclerosis");
      }
      mostAffectedChannels["Multiple Sclerosis"] = (mostAffectedChannels["Multiple Sclerosis"] || []).concat(channel);
    }
  });
  
  if (potentialConditions.length > 0) {
    summary += `\nPotential concerns: ${potentialConditions.join(', ')} based on EEG patterns. `;
    summary += `Most affected channels:\n`;
    Object.entries(mostAffectedChannels).forEach(([condition, channels]) => {
      summary += `- ${condition}: ${channels.join(', ')}\n`;
    });
    summary += `\nThis is not a diagnosis - consult a neurologist for proper evaluation.`;
    hasPotentialIssues = true;
  } else {
    summary += `\nNo significant neurological patterns detected in the EEG data.`;
  }
  
  return {
    summary,
    hasPotentialIssues,
    potentialConditions,
    mostAffectedChannels
  };
}

async function sendMessage() {
  const inputField = document.getElementById("userInput");
  const userMessage = inputField.value.trim();

  if (!userMessage) return; 

  addMessage(userMessage, "user"); 
  inputField.value = "";

  try {
    // Check if user is asking about health report
    if (isHealthReportQuestion(userMessage)) {
      const analysisData = getAnalysisData();
      
      if (!analysisData) {
        addMessage("I couldn't find your EEG analysis data. Please upload and analyze your EEG data first in the MyData section, then return here to ask about your results.", "bot");
        return;
      }
      
      // Generate health summary
      const healthSummary = generateHealthSummary(analysisData);
      
      if (!healthSummary) {
        addMessage("I couldn't process your EEG analysis data. Please try analyzing your data again in the MyData section.", "bot");
        return;
      }
      
      // Show typing indicator
      const typingIndicator = document.createElement("div");
      typingIndicator.id = "typing-indicator";
      typingIndicator.classList.add("msg-container", "bot-msg");
      typingIndicator.innerHTML = `<div class="msg">Analyzing your EEG data...</div>`;
      document.getElementById("chatbot").appendChild(typingIndicator);
      document.getElementById("chatbot").scrollTop = document.getElementById("chatbot").scrollHeight;
      
      if (healthSummary.hasPotentialIssues) {
        // Send detailed analysis to AI
        const prompt = `The user asked: "${userMessage}". Here is their EEG analysis summary:
${healthSummary.summary}

Please provide a compassionate, clear explanation about these findings in simple terms (8th grade reading level). 
Focus on these potential conditions: ${healthSummary.potentialConditions.join(', ')}.
Explain what these EEG patterns might indicate scientifically, but emphasize this is not a diagnosis.
Mention that many factors can affect EEG readings.
Provide balanced information about next steps and the importance of professional evaluation.
Keep response under 300 words.`;
        
        const aiResponse = await getAIResponse(prompt);
        
        // Remove typing indicator
        document.getElementById("typing-indicator").remove();
        
        addMessage("Here's my analysis of your EEG data:", "bot");
        addMessage(aiResponse, "bot");
        addMessage("Remember, I'm an AI assistant and this isn't medical advice. For a proper evaluation, please consult a neurologist who can consider your complete medical history and perform clinical examinations.", "bot");
      } else {
        // Remove typing indicator
        document.getElementById("typing-indicator").remove();
        
        addMessage("I've analyzed your EEG data and here's what I found:", "bot");
        addMessage("Your brainwave patterns appear within normal ranges for all frequency bands (Delta, Theta, Alpha, Beta, Gamma).", "bot");
        addMessage("No significant patterns associated with Alzheimer's, Parkinson's, Multiple Sclerosis, or other neurological conditions were detected in your EEG data.", "bot");
        addMessage("However, EEG is just one indicator of brain health. If you have any concerns, it's always good to consult with a healthcare professional.", "bot");
      }
      
      return;
    }
    
    // Regular chatbot response
    const response = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        contents: [{ parts: [{ text: userMessage }] }], 
      }),
    });

    const data = await response.json();
    const botReply = data.candidates?.[0]?.content?.parts?.[0]?.text || "I'm sorry, I couldn't generate a response.";

    addMessage(botReply, "bot"); 
  } catch (error) {
    console.error("Error:", error);
    addMessage("Error fetching response. Please try again!", "bot");
    
    // Remove typing indicator if it exists
    const typingIndicator = document.getElementById("typing-indicator");
    if (typingIndicator) {
      typingIndicator.remove();
    }
  }
}

async function getAIResponse(prompt) {
  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }], 
      }),
    });

    const data = await response.json();
    return data.candidates?.[0]?.content?.parts?.[0]?.text || "I couldn't generate a detailed analysis. Please consult a neurologist for professional evaluation.";
  } catch (error) {
    console.error("AI Error:", error);
    return "I encountered an error analyzing your data. Please try again later or consult a neurologist directly.";
  }
}

function addMessage(message, sender) {
  const chatBox = document.getElementById("chatbot");

  const msgContainer = document.createElement("div");
  msgContainer.classList.add("msg-container", sender === "user" ? "user-msg" : "bot-msg");

  const msgBubble = document.createElement("div");
  msgBubble.classList.add("msg");
  msgBubble.textContent = message;

  msgContainer.appendChild(msgBubble);
  chatBox.appendChild(msgContainer);

  chatBox.scrollTop = chatBox.scrollHeight; 
}

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("userInput").addEventListener("keypress", event => {
    if (event.key === "Enter") {
      sendMessage();
    }
  });
});

function goToHome() {
  window.location.href = 'Homepage.html';
}

function goToAboutUs() {
  window.location.href = 'AboutUs.html';
}

function goToMyData() {
  window.location.href = 'selfprofile.html';
}

function goToOnlineData() {
  window.location.href = 'Onlinedata.html';
}

function goToChatbot() {
  window.location.href = 'Chatbot.html';
}