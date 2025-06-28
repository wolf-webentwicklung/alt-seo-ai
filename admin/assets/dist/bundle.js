/*!
 * AltSEO AI+ Vue.js Bundle - Production Build
 * Built with Vue 3.3.4
 */
/* phpcs:disable */
// This is a compiled JavaScript bundle, not PHP code - disable all PHPCS checks
/* phpcs:ignoreFile */
(function(){
'use strict';

// Add styles for message classes
const styleEl = document.createElement('style');
styleEl.textContent = `
  .success-message {
		color: #28a745;
		font-weight: 500;
		padding: 5px 10px;
		background-color: rgba(40, 167, 69, 0.1);
		border-left: 3px solid #28a745;
		margin-left: 10px;
  }
  .error-message {
		color: #dc3545;
		font-weight: 500;
		padding: 5px 10px;
		background-color: rgba(220, 53, 69, 0.1);
		border-left: 3px solid #dc3545;
		margin-left: 10px;
  }
`;
document.head.appendChild(styleEl);

// Vue 3 Production Bundle - Minimal version for AltSEO AI+
const { createApp, ref, reactive, onMounted, computed, watch } = Vue;

// Settings Form Component
const SettingsForm = {
		  props: ['apiKey', 'models', 'selectedModel', 'visionModels', 'selectedVisionModel', 'keywords', 'autoGenerate', 'keywordCount', 'seoKeywordsCount'],
		  emits: ['save', 'refreshModels', 'refreshVisionModels'],
		  template: `
		<div class="altseo-settings-form">
		  <div class="altseo-logo-section">
        <img :src="logoUrl" alt="AltSEO AI+ Logo" class="altseo-form-logo" v-if="logoUrl" />
        <table class="form-table">
          <tbody>
            <tr>
              <th>
                <label>OpenAI Key:</label>
                <span class="field-description">Enter your OpenAI API key to enable AI-powered alt text and keyword generation</span>
              </th>
              <td><input type="password" size="57" v-model="localApiKey" class="regular-text" /></td>
            </tr>
            <tr>
              <th>
                <label>OpenAI Model (Keywords):</label>
                <span class="field-description">Select which OpenAI model to use for generating keywords and text content</span>
              </th>
              <td>
                <select v-model="localSelectedModel" style="width:200px; margin-right: 15px;">
                  <option v-for="model in models" :key="model" :value="model">{{ model }}</option>
                </select>
                <button @click="handleRefreshModels" type="button" class="button-secondary" :disabled="isRefreshing">
                  <span v-if="isRefreshing" class="spinner is-active" style="float: none; margin-right: 5px; width: 16px; height: 16px;"></span>
                  {{ isRefreshing ? 'Refreshing...' : 'Refresh Models' }}
                </button>
                <div v-if="refreshMessage" :class="refreshMessageClass" style="margin-top: 5px;">{{ refreshMessage }}</div>
              </td>
            </tr>
            <tr>
              <th>
                <label>OpenAI Vision Model (Images):</label>
                <span class="field-description">Select which vision-capable OpenAI model to use for generating image alt texts</span>
              </th>
              <td>
                <select v-model="localSelectedVisionModel" style="width:200px; margin-right: 15px;">
                  <option v-for="model in visionModels" :key="model" :value="model">{{ model }}</option>
                </select>
                <button @click="handleRefreshVisionModels" type="button" class="button-secondary" :disabled="isRefreshingVision">
                  <span v-if="isRefreshingVision" class="spinner is-active" style="float: none; margin-right: 5px; width: 16px; height: 16px;"></span>
                  {{ isRefreshingVision ? 'Refreshing...' : 'Refresh Vision Models' }}
                </button>
                <div v-if="refreshVisionMessage" :class="refreshVisionMessageClass" style="margin-top: 5px;">{{ refreshVisionMessage }}</div>
              </td>
            </tr>
            <tr>
              <th>
                <label>SEO Keywords:</label>
                <span class="field-description">Default keywords to use when specific post keywords are not available</span>
              </th>
              <td>
                <div class="keywords-input-container">
                  <input 
                    ref="keywordInput"
                    type="text" 
                    v-model="currentKeyword" 
                    @keydown="handleKeywordInput"
                    @blur="addCurrentKeyword"
                    placeholder="Type a keyword and press Enter or comma to add"
                    class="keyword-main-input regular-text"
                  />
                  <div class="keywords-display" v-if="keywordArray.length > 0">
                    <span v-for="(keyword, index) in keywordArray" :key="index" class="keyword-tag">
                      {{ keyword.trim() }}
                      <a @click="removeKeyword(index)" href="javascript:void(0)" class="remove-tag">×</a>
                    </span>
                  </div>
                  <div class="keyword-help-text">
                    <small>Type keywords and press <strong>Enter</strong> or add a <strong>comma</strong> to create tags. Click × to remove a keyword.</small>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th>
                <label>SEO Keywords for Alt Tags:</label>
                <span class="field-description">How many random SEO keywords to include in generated alt tags</span>
              </th>
              <td>
                <select v-model="localSeoKeywordsCount" style="width:100px">
                  <option v-for="num in 10" :key="num" :value="num">{{ num }}</option>
                </select>
              </td>
            </tr>
            <tr>
              <th>
                <label>Auto Generate on Save:</label>
                <span class="field-description">Automatically generate keywords and alt tags when posts are saved or updated</span>
              </th>
              <td>
                <div class="ui-switch-container">
                  <label class="ui-switch">
                    <input ref="autoGenerateCheckbox" type="checkbox" v-model="localAutoGenerate" />
                    <span class="ui-switch-slider" :class="{ 'checked': localAutoGenerate }"></span>
                  </label>
                  <span class="ui-switch-label">{{ localAutoGenerate ? 'Enabled' : 'Disabled' }}</span>
                </div>
              </td>
            </tr>
            <tr>
              <th>
                <label>Keywords to Generate:</label>
                <span class="field-description">Number of keywords the AI should generate for each post</span>
              </th>
              <td>
                <select v-model="localKeywordCount" style="width:100px">
                  <option v-for="num in 10" :key="num" :value="num">{{ num }}</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
		  </div>
      
		  <p class="submit">
        <button @click="saveSettings" type="button" class="button button-primary" :disabled="isSaving">
          {{ isSaving ? 'Saving...' : 'Save Changes' }}
        </button>
        <span v-if="saveMessage" class="save-message" :class="saveMessageClass">{{ saveMessage }}</span>
		  </p>
		</div>
		  `,
		  setup(props, { emit }) {
    const localApiKey = ref(props.apiKey);
    const localSelectedModel = ref(props.selectedModel);
    const localSelectedVisionModel = ref(props.selectedVisionModel);
    const localKeywords = ref(props.keywords);
    const localAutoGenerate = ref(props.autoGenerate);
    const localKeywordCount = ref(props.keywordCount);
    const localSeoKeywordsCount = ref(props.seoKeywordsCount);
    const isSaving = ref(false);
    const isRefreshing = ref(false);
    const isRefreshingVision = ref(false);
    const saveMessage = ref('');
    const saveMessageClass = ref('');
    const refreshMessage = ref('');
    const refreshMessageClass = ref('');
    const refreshVisionMessage = ref('');
    const refreshVisionMessageClass = ref('');
    
    // Watch for prop changes and update local state
    watch(() => props.autoGenerate, (newValue) => {
					  localAutoGenerate.value = newValue;
    });
    
    watch(() => props.apiKey, (newValue) => {
					  localApiKey.value = newValue;
    });
    
    watch(() => props.selectedModel, (newValue) => {
					  localSelectedModel.value = newValue;
    });
    
    watch(() => props.selectedVisionModel, (newValue) => {
					  localSelectedVisionModel.value = newValue;
    });
    
    watch(() => props.keywords, (newValue) => {
					  localKeywords.value = newValue;
    });
    
    watch(() => props.keywordCount, (newValue) => {
					  localKeywordCount.value = newValue;
    });
    
    watch(() => props.seoKeywordsCount, (newValue) => {
					  localSeoKeywordsCount.value = newValue;
    });
    
    // DOM element references - must be declared before watchers that use them
    const autoGenerateCheckbox = ref(null); // Reference to the DOM checkbox element
    
    // Watch for changes and force DOM update for checkbox
    watch(() => localAutoGenerate.value, (newValue) => {
					  // Force DOM checkbox to sync with our reactive value
					  if (autoGenerateCheckbox.value) {
		  // Directly set the checked property on the DOM element
		  autoGenerateCheckbox.value.checked = newValue;
        
		  // Force an update of the parent label's class to ensure CSS pseudo-elements render correctly
		  const parentLabel = autoGenerateCheckbox.value.closest('.ui-switch');
		  if (parentLabel) {
							  const sliderSpan = parentLabel.querySelector('.ui-switch-slider');
							  if (sliderSpan) {
				if (newValue) {
									  sliderSpan.classList.add('checked');
				} else {
								  sliderSpan.classList.remove('checked');
				}
							  }
		  }
        
		  // Trigger a DOM update to make the browser re-evaluate pseudo-elements
		  autoGenerateCheckbox.value.dispatchEvent(new Event('change', { bubbles: true }));
					  }
    }, { immediate: true });
    // Get the logo URL safely
    const logoUrl = computed(() => {
					  return (typeof window !== 'undefined' && window.altSeoData?.pluginUrl) 
				? window.altSeoData.pluginUrl + 'assets/images/alt-seo-ai-logo.png'
				: '';
    });

    // Initialize checkbox state on component mount
    onMounted(() => {
					  // Ensure the checkbox state is correctly set on initial mount
					  if (autoGenerateCheckbox.value) {
		  autoGenerateCheckbox.value.checked = localAutoGenerate.value;
        
		  // Force the slider to have the correct class
		  const parentLabel = autoGenerateCheckbox.value.closest('.ui-switch');
		  if (parentLabel) {
							  const sliderSpan = parentLabel.querySelector('.ui-switch-slider');
							  if (sliderSpan) {
				if (localAutoGenerate.value) {
									  sliderSpan.classList.add('checked');
				} else {
								  sliderSpan.classList.remove('checked');
				}
							  }
		  }

		  // Trigger a DOM update
		  autoGenerateCheckbox.value.dispatchEvent(new Event('change', { bubbles: true }));
					  }
    });
    
    // Keyword input handling
    const currentKeyword = ref('');
    const keywordInput = ref(null);
    
    // Computed property to convert comma-separated string to array
    const keywordArray = computed(() => {
					  if (!localKeywords.value) return [];
					  return localKeywords.value.split(',').filter(k => k.trim() !== '');
    });
    
    // Methods for keyword handling
    const addCurrentKeyword = () => {
					  if (currentKeyword.value.trim()) {
		  const newKeyword = currentKeyword.value.trim();
		  const existing = keywordArray.value;
		  if (!existing.includes(newKeyword)) {
							  const updated = existing.length > 0 ? [...existing, newKeyword] : [newKeyword];
							  localKeywords.value = updated.join(', ');
		  }
		  currentKeyword.value = '';
					  }
    };
    
    const removeKeyword = (index) => {
					  const updated = keywordArray.value.filter((_, i) => i !== index);
					  localKeywords.value = updated.join(', ');
    };
    
    const handleKeywordInput = (event) => {
					  if (event.key === 'Enter' || event.key === ',') {
		  event.preventDefault();
		  addCurrentKeyword();
					  } else if (event.key === 'Backspace' && currentKeyword.value === '' && keywordArray.value.length > 0) {
        // Remove last keyword if backspace is pressed on empty input
        removeKeyword(keywordArray.value.length - 1);
					  }
    };

    const saveSettings = async () => {
					  isSaving.value = true;
					  saveMessage.value = '';
      
					  try {
		  const formData = new FormData();
		  // Clean API key before submitting (remove spaces, tabs, newlines)
		  const cleanedApiKey = localApiKey.value ? localApiKey.value.trim().replace(/\s+/g, '') : '';
        
		  formData.append('action', 'altseo_save_settings');
		  formData.append('altseo_ai_key', cleanedApiKey);
		  formData.append('altseo_ai_model', localSelectedModel.value);
		  formData.append('altseo_vision_ai_model', localSelectedVisionModel.value);
		  formData.append('altseo_global_keywords', localKeywords.value);
		  formData.append('altseo_enabled', localAutoGenerate.value ? 'yes' : '');
		  formData.append('altseo_keyword_num', localKeywordCount.value);
		  formData.append('altseo_seo_keywords_count', localSeoKeywordsCount.value);
		  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.nonce) || '');

		  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
							  method: 'POST',
							  body: formData
		  });

		  const result = await response.json();
        
		  if (result.success) {
					  saveMessage.value = '✓ Saved Successfully!';
					  saveMessageClass.value = 'success-message';
          
					  // Update the parent component's state to reflect saved values
					  emit('save', {
				apiKey: localApiKey.value,
				selectedModel: localSelectedModel.value,
				selectedVisionModel: localSelectedVisionModel.value,
				keywords: localKeywords.value,
				autoGenerate: localAutoGenerate.value,
				keywordCount: localKeywordCount.value,
				seoKeywordsCount: localSeoKeywordsCount.value
							  });
          
					  setTimeout(() => saveMessage.value = '', 3000);
		  } else {
					// Handle error messages with better formatting
					let errorMessage = 'Unknown error';
          
					if (result.data) {
				if (typeof result.data === 'string') {
						  errorMessage = result.data;
				} else if (result.data.message) {
							errorMessage = result.data.message;
				}
						}
          
					saveMessage.value = '✗ ' + errorMessage;
					saveMessageClass.value = 'error-message';
					// Keep error message visible longer
					setTimeout(() => saveMessage.value = '', 5000);
		  }
			  } catch (error) {
					saveMessage.value = '✗ Network error: ' + error.message;
					saveMessageClass.value = 'error-message';
					setTimeout(() => saveMessage.value = '', 5000);
					  } finally {
isSaving.value = false;
					  }
    };

    // Watch for model refresh
    const handleRefreshModels = async () => {
					  isRefreshing.value = true;
					  refreshMessage.value = '';
      
					  try {
		  const formData = new FormData();
		  formData.append('action', 'altseo_refresh_models');
		  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.nonce) || '');

		  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
							  method: 'POST',
							  body: formData
		  });

		  const result = await response.json();
        
		  if (result.success) {
					  // Update the models in the parent component
					  emit('refreshModels', result.data.models);
					  refreshMessage.value = '✓ ' + result.data.message;
					  refreshMessageClass.value = 'success-message';
          
					  // Clear message after 3 seconds
					  setTimeout(() => refreshMessage.value = '', 3000);
		  } else {
					// Handle both string and object error responses
					let errorMessage = 'Failed to refresh models';
					if (result.data) {
				if (typeof result.data === 'string') {
						  errorMessage = result.data;
				} else if (result.data.message) {
							errorMessage = result.data.message;
				}
						}
					refreshMessage.value = '✗ ' + errorMessage;
					refreshMessageClass.value = 'error-message';
					setTimeout(() => refreshMessage.value = '', 5000);
		  }
			  } catch (error) {
					refreshMessage.value = '✗ Network error while refreshing models';
					refreshMessageClass.value = 'error-message';
					setTimeout(() => refreshMessage.value = '', 5000);
					  } finally {
isRefreshing.value = false;
					  }
    };

    // Watch for vision model refresh
    const handleRefreshVisionModels = async () => {
					  isRefreshingVision.value = true;
					  refreshVisionMessage.value = '';
      
					  try {
		  const formData = new FormData();
		  formData.append('action', 'altseo_refresh_vision_models');
		  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.nonce) || '');

		  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
							  method: 'POST',
							  body: formData
		  });

		  const result = await response.json();
        
		  if (result.success) {
					  // Update the vision models in the parent component
					  emit('refreshVisionModels', result.data.models);
					  refreshVisionMessage.value = '✓ ' + result.data.message;
					  refreshVisionMessageClass.value = 'success-message';
          
					  // Clear message after 3 seconds
					  setTimeout(() => refreshVisionMessage.value = '', 3000);
		  } else {
					// Handle both string and object error responses
					let errorMessage = 'Failed to refresh vision models';
					if (result.data) {
				if (typeof result.data === 'string') {
						  errorMessage = result.data;
				} else if (result.data.message) {
							errorMessage = result.data.message;
				}
						}
					refreshVisionMessage.value = '✗ ' + errorMessage;
					refreshVisionMessageClass.value = 'error-message';
					setTimeout(() => refreshVisionMessage.value = '', 5000);
		  }
			  } catch (error) {
					refreshVisionMessage.value = '✗ Network error while refreshing vision models';
					refreshVisionMessageClass.value = 'error-message';
					setTimeout(() => refreshVisionMessage.value = '', 5000);
					  } finally {
isRefreshingVision.value = false;
					  }
    };

    return {
					  localApiKey,
					  localSelectedModel,
					  localSelectedVisionModel,
					  localKeywords,
					  localAutoGenerate,
					  localKeywordCount,
					  localSeoKeywordsCount,
					  isSaving,
					  isRefreshing,
					  isRefreshingVision,
					  saveMessage,
					  saveMessageClass,
					  refreshMessage,
					  refreshMessageClass,
					  refreshVisionMessage,
					  refreshVisionMessageClass,
					  currentKeyword,
					  keywordInput,
					  autoGenerateCheckbox,
					  keywordArray,
					  logoUrl,
					  saveSettings,
					  handleRefreshModels,
					  handleRefreshVisionModels,
					  addCurrentKeyword,
					  removeKeyword,
					  handleKeywordInput
    };
			  }
};

// Bulk Tools Component
const BulkTools = {
		  emits: ['generateKeywords', 'generateAlts'],
		  template: `
		<div class="altseo-bulk-tools">
		  <h3 style="margin-top: 0; margin-bottom: 20px; color: #23282d;">Bulk Generation Tools</h3>
		  <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label>Generate Keywords:</label>
              <span class="field-description">Process all existing posts to generate keywords in bulk</span>
            </th>
            <td>
              <div class="altseo-bulk-action-buttons">
                <button @click="generateKeywords" type="button" class="button-secondary" :disabled="isGeneratingKeywords">
                  {{ isGeneratingKeywords ? 'Generating...' : 'Generate Keywords For All Posts Now' }}
                </button>
                <button v-if="isGeneratingKeywords" @click="stopBulkGeneration('keywords')" type="button" class="button altseo-stop-button">
                  Stop Generation
                </button>
              </div>
              <div class="progress_section" :style="{ display: keywordProgress.show ? 'block' : 'none' }">
                <div class="progress_bar" :style="keywordProgressStyle" :data-percentage="keywordProgress.percentage + '%'"></div>
                <div class="progress_report" :class="{ 'process-halted': keywordProgress.halted }">{{ keywordProgress.message }}</div>
              </div>
              <div v-if="keywordSuccess" class="bulk-success-message">
                ✓ Keywords generated successfully for all posts!
              </div>
            </td>
          </tr>
          <tr>
            <th>
              <label>Generate Alt Tags:</label>
              <span class="field-description">Process all existing posts to generate alt tags for images in bulk</span>
            </th>
            <td>
              <div class="altseo-bulk-action-buttons">
                <button @click="generateAlts" type="button" class="button-secondary" :disabled="isGeneratingAlts">
                  {{ isGeneratingAlts ? 'Generating...' : 'Generate Alt Tags For All Posts Now' }}
                </button>
                <button v-if="isGeneratingAlts" @click="stopBulkGeneration('alts')" type="button" class="button altseo-stop-button">
                  Stop Generation
                </button>
              </div>
              <div class="progress_section2" :style="{ display: altProgress.show ? 'block' : 'none' }">
                <div class="progress_bar2" :style="altProgressStyle" :data-percentage="altProgress.percentage + '%'"></div>
                <div class="progress_report2" :class="{ 'process-halted': altProgress.halted }">{{ altProgress.message }}</div>
              </div>
              <div v-if="altSuccess" class="bulk-success-message">
                ✓ Alt tags generated successfully for all posts!
              </div>
            </td>
          </tr>
        </tbody>
		  </table>
		</div>
		  `,
		  setup(props, { emit }) {
    const isGeneratingKeywords = ref(false);
    const isGeneratingAlts = ref(false);
    const keywordProgress = reactive({ 
					  show: false, 
					  percentage: 0, 
					  message: '0% complete....',
					  halted: false
    });
    const altProgress = reactive({ 
					  show: false, 
					  percentage: 0, 
					  message: '0% complete....',
					  halted: false
    });
    const keywordSuccess = ref(false);
    const altSuccess = ref(false);
    const processStopped = ref(false);

    const generateKeywords = async () => {
					  if (!confirm('Are you sure you want to generate keywords for all posts? This may take a while.')) return;
      
					  isGeneratingKeywords.value = true;
					  keywordSuccess.value = false;
					  processStopped.value = false; // Reset stop flag for new process
					  keywordProgress.show = true;
					  keywordProgress.percentage = 0;
					  keywordProgress.message = '0% complete....';
					  keywordProgress.halted = false; // Reset halted state
      
					  try {
		  await processWithProgress('ajax_bulk_generate_keyword', keywordProgress);
		  if (!keywordProgress.halted) { // Only show success if not halted
							  keywordSuccess.value = true;
							  emit('generateKeywords');
          
							  // Hide success message after 5 seconds
							  setTimeout(() => {
						  keywordSuccess.value = false;
										}, 5000);
		  }
					  } catch (error) {
        console.error('Error generating keywords:', error);
        if (!keywordProgress.halted) {
							  keywordProgress.message = 'Error occurred during generation.';
        }
					  } finally {
isGeneratingKeywords.value = false;
setTimeout(() => {
						if (!keywordProgress.halted) {
			keywordProgress.show = false;
											}
}, 3000);
					  }
    };

    const generateAlts = async () => {
									  if (!confirm('Are you sure you want to generate alt tags for all posts? This may take a while.')) return;
      
									  isGeneratingAlts.value = true;
									  altSuccess.value = false;
									  processStopped.value = false; // Reset stop flag for new process
									  altProgress.show = true;
									  altProgress.percentage = 0;
									  altProgress.message = '0% complete....';
									  altProgress.halted = false; // Reset halted state
      
									  try {
		  await processWithProgress('ajax_bulk_generate_alt', altProgress);
		  if (!altProgress.halted) { // Only show success if not halted
											  altSuccess.value = true;
											  emit('generateAlts');
          
											  // Hide success message after 5 seconds
											  setTimeout(() => {
								  altSuccess.value = false;
																}, 5000);
		  }
									  } catch (error) {
        console.error('Error generating alt tags:', error);
        if (!altProgress.halted) {
									  altProgress.message = 'Error occurred during generation.';
        }
									  } finally {
isGeneratingAlts.value = false;
setTimeout(() => {
									  if (!altProgress.halted) {
				altProgress.show = false;
									  }
}, 3000);
									  }
    };

    const processWithProgress = async (action, progressObj) => {
									  let attempts = 0;
									  const maxAttempts = 1000; // Prevent infinite loops
      
									  while (progressObj.percentage < 100 && attempts < maxAttempts && !processStopped.value) {
		  attempts++;
        
		  try {
											  const formData = new FormData();
											  formData.append('action', action);
											  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.nonce) || '');

											  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
								  method: 'POST',
								  body: formData,
								  credentials: 'same-origin' // Include cookies for WordPress auth
																});

																if (!response.ok) {
									  throw new Error(`HTTP error! status: ${response.status}`);
																}

																// Check if process has been stopped
																if (processStopped.value) {
				break;
																}

																const responseText = await response.text();
          
																// Try to parse as JSON first (new format), fallback to plain text (legacy)
																let result;
																try {
            result = JSON.parse(responseText);
																} catch (e) {
// Legacy format - plain text percentage
const percentage = parseInt(responseText) || 0;
result = {
																	  success: true,
																	  data: {
percentage: percentage,
message: percentage === -1 ? 'Process was stopped' : `${percentage}% complete....`
																	  }
            };
																}
          
																if (result.success && result.data) {
const newPercentage = parseInt(result.data.percentage) || 0;
const message = result.data.message || `${newPercentage}% complete....`;
            
// Check if backend signaled that process was stopped
if (newPercentage === -1) {
																	progressObj.halted = true;
																	progressObj.message = '⚠️ Process halted by user';
																	break;
}
            
if (newPercentage >= 0) {
														  progressObj.percentage = newPercentage;
														  progressObj.message = message;
              
														  if (newPercentage >= 100) {
	  progressObj.message = '100% complete - Finished successfully!';
	  break;
																												}
}
																} else {
// Handle error response
const errorMessage = result.data || 'Unknown error occurred';
console.error('API Error:', errorMessage);
progressObj.message = `Error: ${errorMessage}`;
throw new Error(errorMessage);
																}
          
																// Small delay between requests to prevent overwhelming the server
																await new Promise(resolve => setTimeout(resolve, 1000));
          
} catch (error) {
															  console.error('Error during bulk generation:', error);
															  progressObj.message = `Error: ${error.message || 'Unknown error occurred'}`;
															  throw error;
		  }
													  }
      
									  if (attempts >= maxAttempts) {
        progressObj.message = 'Process timed out. Please try again.';
        throw new Error('Process exceeded maximum attempts');
									  }
    };

    // Computed styles for progress bars
    const keywordProgressStyle = computed(() => ({
									  background: `linear-gradient(to right, #2271b1 ${keywordProgress.percentage}%, #f0f0f1 ${keywordProgress.percentage}%)`
    }));
    
    const altProgressStyle = computed(() => ({
					  background: `linear-gradient(to right, #2271b1 ${altProgress.percentage}%, #f0f0f1 ${altProgress.percentage}%)`
    }));

    const stopBulkGeneration = async (type) => {
					  try {
		  const progressObj = type === 'keywords' ? keywordProgress : altProgress;
        
		  // Set frontend flag to stop the process loop
		  processStopped.value = true;
        
		  // Send AJAX request to stop the generation process on the backend
		  const formData = new FormData();
		  formData.append('action', 'altseo_stop_bulk_generation');
		  formData.append('type', type); // Specify which type to stop
		  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.nonce) || '');

		  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
							  method: 'POST',
							  body: formData,
							  credentials: 'same-origin'
		  });

		  if (!response.ok) {
					  throw new Error(`HTTP error! status: ${response.status}`);
		  }

		  const result = await response.json();
        
		  if (result.success) {
					// Update UI to reflect stopped process
					progressObj.halted = true;
					progressObj.message = '⚠️ Process halted by user';
          
					// Reset generation states
					if (type === 'keywords') {
				isGeneratingKeywords.value = false;
						} else {
            isGeneratingAlts.value = false;
						}
          
				  // Hide progress bar after a delay and reset flags
				  setTimeout(() => {
					progressObj.show = false;
					progressObj.halted = false;
					processStopped.value = false; // Reset the stop flag for next run
						}, 5000);
		  } else {
				  console.error('Failed to stop generation process:', result);
				  processStopped.value = false; // Reset flag if stop failed
		  }
			  } catch (error) {
					console.error('Error stopping generation process:', error);
					processStopped.value = false; // Reset flag on error
					  }
    };

    return {
									  isGeneratingKeywords,
									  isGeneratingAlts,
									  keywordProgress,
									  altProgress,
									  keywordSuccess,
									  altSuccess,
									  keywordProgressStyle,
									  altProgressStyle,
									  generateKeywords,
									  generateAlts,
									  stopBulkGeneration
    };
					  }
};

// Main App Component
const AltSeoApp = {
		  components: {
	  SettingsForm,
	  BulkTools
			  },
		  template: `
		<div id="altseo-app">
		  <h2>Alt Seo AI + Settings</h2>
      
		  <div class="altseo-settings-section">
        <SettingsForm 
          :api-key="apiKey"
          :models="availableModels"
          :selected-model="selectedModel"
          :vision-models="availableVisionModels"
          :selected-vision-model="selectedVisionModel"
          :keywords="globalKeywords"
          :auto-generate="enabled"
          :keyword-count="keywordNum"
          :seo-keywords-count="seoKeywordsCount"
          @save="saveSettings"
          @refresh-models="handleRefreshModels"
          @refresh-vision-models="handleRefreshVisionModels">
        </SettingsForm>
		  </div>
      
		  <div class="altseo-tools-section">
        <BulkTools
          ref="bulkTools"
          @generate-keywords="generateAllKeywords" 
          @generate-alts="generateAllAlts">
        </BulkTools>
		  </div>
		</div>
		  `,
		  setup() {
		const apiKey = ref((typeof window !== 'undefined' && window.altSeoData?.apiKey) || '');
		const availableModels = ref((typeof window !== 'undefined' && window.altSeoData?.models) || ['gpt-3.5-turbo']);
		const selectedModel = ref((typeof window !== 'undefined' && window.altSeoData?.selectedModel) || 'gpt-3.5-turbo');
		const availableVisionModels = ref((typeof window !== 'undefined' && window.altSeoData?.visionModels) || ['gpt-4o-mini']);
		const selectedVisionModel = ref((typeof window !== 'undefined' && window.altSeoData?.selectedVisionModel) || 'gpt-4o-mini');
		const globalKeywords = ref((typeof window !== 'undefined' && window.altSeoData?.globalKeywords) || '');
		const enabled = ref((typeof window !== 'undefined' && window.altSeoData?.enabled) || false);
		const keywordNum = ref((typeof window !== 'undefined' && window.altSeoData?.keywordNum) || 1);
		const seoKeywordsCount = ref((typeof window !== 'undefined' && window.altSeoData?.seoKeywordsCount) || 1);
		const isRefreshing = ref(false);
		const refreshMessage = ref('');

		// Ensure selected models are valid on initialization
		onMounted(() => {
				  if (!availableModels.value.includes(selectedModel.value)) {
			selectedModel.value = availableModels.value.includes('gpt-3.5-turbo') 
			  ? 'gpt-3.5-turbo' 
			  : availableModels.value[0];
					  }
				  if (!availableVisionModels.value.includes(selectedVisionModel.value)) {
        selectedVisionModel.value = availableVisionModels.value.includes('gpt-4o-mini') 
          ? 'gpt-4o-mini' 
          : availableVisionModels.value[0];
					  }
    });

		const saveSettings = (formData) => {
				  // Update the main app state with the saved values
				  if (formData) {
			if (formData.apiKey !== undefined) apiKey.value = formData.apiKey;
			if (formData.selectedModel !== undefined) selectedModel.value = formData.selectedModel;
			if (formData.selectedVisionModel !== undefined) selectedVisionModel.value = formData.selectedVisionModel;
			if (formData.keywords !== undefined) globalKeywords.value = formData.keywords;
			if (formData.autoGenerate !== undefined) enabled.value = formData.autoGenerate;
			if (formData.keywordCount !== undefined) keywordNum.value = formData.keywordCount;
			if (formData.seoKeywordsCount !== undefined) seoKeywordsCount.value = formData.seoKeywordsCount;
					  }
				  console.log('Settings saved and state updated');
		};

		const refreshModels = async () => {
					  isRefreshing.value = true;
					  refreshMessage.value = '';
      
					  try {
		  const formData = new FormData();
		  formData.append('action', 'altseo_refresh_models');
		  formData.append('nonce', (typeof window !== 'undefined' && window.altSeoData?.refreshModelsNonce) || '');

		  const response = await fetch((typeof window !== 'undefined' && window.altSeoData?.ajaxUrl) || '/wp-admin/admin-ajax.php', {
								  method: 'POST',
								  body: formData
		  });

		  const result = await response.json();
        
		  if (result.success) {
								  handleRefreshModels(result.data.models);
								  refreshMessage.value = 'Models refreshed successfully!';
								  setTimeout(() => {
							refreshMessage.value = '';
											  }, 3000);
											  console.log('Models refreshed successfully:', result.data.models);
		  } else {
								refreshMessage.value = 'Failed to refresh models. Please try again.';
								console.error('Failed to refresh models:', result.data);
		  }
					  } catch (error) {
        refreshMessage.value = 'Error refreshing models. Please check your connection.';
        console.error('Error refreshing models:', error);
							  } finally {
isRefreshing.value = false;
							  }
		};

		const handleRefreshModels = (models) => {
					  if (models && Array.isArray(models)) {
		  availableModels.value = models;
        
		  // If current selected model is not in the new list, select the first available or default
		  if (!models.includes(selectedModel.value)) {
								  selectedModel.value = models.includes('gpt-3.5-turbo') ? 'gpt-3.5-turbo' : models[0];
		  }
							  }
		};

		const handleRefreshVisionModels = (models) => {
					  if (models && Array.isArray(models)) {
		  availableVisionModels.value = models;
        
		  // If current selected vision model is not in the new list, select the first available or default
		  if (!models.includes(selectedVisionModel.value)) {
								  selectedVisionModel.value = models.includes('gpt-4o-mini') ? 'gpt-4o-mini' : models[0];
		  }
							  }
		};

		const generateAllKeywords = () => {
					  console.log('Generating keywords for all posts');
		};

		const generateAllAlts = () => {
					  console.log('Generating alt texts for all posts');
		};

		return {
					  apiKey,
					  availableModels,
					  selectedModel,
					  availableVisionModels,
					  selectedVisionModel,
					  globalKeywords,
					  enabled,
					  keywordNum,
					  seoKeywordsCount,
					  isRefreshing,
					  refreshMessage,
					  saveSettings,
					  refreshModels,
					  generateAllKeywords,
					  generateAllAlts,
					  handleRefreshModels,
					  handleRefreshVisionModels
		};
				  }
};

// Initialize the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
		  const appElement = document.getElementById('altseo-app');
		  if (appElement && window.altSeoData) {
	  try {
				  // Check if Vue is available
				  if (typeof Vue === 'undefined') {
			throw new Error('Vue.js not loaded');
						}
      
				  // Wait a bit to ensure styles are loaded before mounting
				  setTimeout(() => {
					try {
							  // Add loading class to prevent layout shift
							  appElement.classList.add('loading');
          
							  // Store app instance globally for extensions (like bulk generation stop feature)
							  window.altSeoAppInstance = null;
          
							  // Mount the Vue app
							  const app = createApp(AltSeoApp);
							  const vm = app.mount('#altseo-app');
          
							  // Store app instance globally for extensions
							  window.altSeoAppInstance = vm;
          
							  // Wait for Vue to render before showing the interface
							  setTimeout(() => {
									const loadingElement = document.getElementById('loading-fallback');
									if (loadingElement) {
				  loadingElement.style.opacity = '0';
				  loadingElement.style.transition = 'opacity 0.3s ease-out';
				  setTimeout(() => {
														  loadingElement.style.display = 'none';
				  }, 300);
							  }
            
								// Remove loading class and show Vue app with transition
								appElement.classList.remove('loading');
								appElement.style.opacity = '1';
            
								console.log('AltSEO AI+: Vue app successfully mounted');
            
								// Emit custom event to notify fallback detector that Vue mounted successfully
								window.dispatchEvent(new CustomEvent('altseo-vue-mounted'));
									  }, 300); // Wait 300ms for Vue to render
          
					} catch (mountError) {
							console.error('AltSEO AI+: Failed to mount Vue app:', mountError);
							appElement.classList.remove('loading');
							// Let the fallback detector handle the error display
					}
						}, 150); // Wait 150ms for styles to load
      
	  } catch (error) {
				console.error('AltSEO AI+: Vue not available:', error);
				// Let the fallback detector handle the error display
	  }
			  }
});

})();
